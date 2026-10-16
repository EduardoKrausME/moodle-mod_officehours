<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * User data export and erasure under Moodle Privacy API.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/** Privacy provider for student and staff scheduling data. */
final class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_user_data_provider,
        \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $tables = [
            "officehours_booking" => ["userid" => "privacy:userid", "subject" => "privacy:booking",
                "notes" => "privacy:booking", "internalnotes" => "privacy:booking", "outcome" => "privacy:booking"],
            "officehours_availability" => ["teacherid" => "privacy:teacher"],
            "officehours_slot" => ["teacherid" => "privacy:teacher"],
            "officehours_exception" => ["teacherid" => "privacy:teacher"],
            "officehours_waitlist" => ["userid" => "privacy:userid"],
            "officehours_notification" => ["userid" => "privacy:userid"],
            "officehours_calendar" => ["userid" => "privacy:userid"],
        ];
        foreach ($tables as $table => $fields) {
            $collection->add_database_table($table, $fields, "privacy:purpose");
        }
        return $collection;
    }

    private static function contexts(int $userid): array {
        global $DB;
        $ids = [];
        $sources = ["officehours_booking" => "userid", "officehours_waitlist" => "userid",
            "officehours_availability" => "teacherid", "officehours_slot" => "teacherid",
            "officehours_exception" => "teacherid", "officehours_notification" => "userid",
            "officehours_calendar" => "userid"];
        foreach ($sources as $table => $column) {
            $activities = $DB->get_fieldset_select($table, "officehoursid", $column . " = :uid", ["uid" => $userid]);
            $ids = array_merge($ids, $activities);
        }
        return array_unique(array_map("intval", $ids));
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $result = new contextlist();
        foreach (self::contexts($userid) as $instanceid) {
            $cm = get_coursemodule_from_instance("officehours", $instanceid);
            if ($cm) {
                $result->add_context(context_module::instance($cm->id)->id);
            }
        }
        return $result;
    }

    public static function get_users_in_context(userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id("officehours", $context->instanceid);
        if (!$cm) {
            return;
        }
        $users = [];
        foreach (["officehours_booking" => "userid", "officehours_waitlist" => "userid",
                "officehours_availability" => "teacherid", "officehours_slot" => "teacherid",
                "officehours_exception" => "teacherid", "officehours_notification" => "userid",
                "officehours_calendar" => "userid"] as $table => $field) {
            $users = array_merge($users, $DB->get_fieldset_select($table, $field,
                "officehoursid = :officeid", ["officeid" => $cm->instance]));
        }
        $userlist->add_users(array_filter(array_unique(array_map("intval", $users))));
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id("officehours", $context->instanceid);
            if (!$cm) {
                continue;
            }
            foreach (["officehours_booking" => "userid", "officehours_waitlist" => "userid",
                    "officehours_availability" => "teacherid", "officehours_slot" => "teacherid",
                    "officehours_exception" => "teacherid", "officehours_notification" => "userid",
                    "officehours_calendar" => "userid"] as $table => $column) {
                $rows = $DB->get_records($table, ["officehoursid" => $cm->instance, $column => $userid]);
                if ($rows) {
                    writer::with_context($context)->export_data([$table],
                        (object)["records" => array_values($rows)]);
                }
            }
        }
    }

    private static function erase(int $instanceid, array $userids): void {
        global $DB;
        $userids = array_filter(array_unique(array_map("intval", $userids)));
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "privacy");
        $params["officeid"] = $instanceid;
        $bookings = $DB->get_records_select("officehours_booking",
            "officehoursid = :officeid AND userid " . $insql, $params);
        foreach ($bookings as $booking) {
            \mod_officehours\local\calendar_service::remove((int)$booking->id);
            $DB->delete_records("officehours_notification", ["bookingid" => $booking->id]);
            $DB->delete_records("officehours_booking", ["id" => $booking->id]);
        }
        foreach ([$DB->get_records_select("officehours_waitlist", "officehoursid = :officeid AND userid " . $insql, $params)] as $records) {
            foreach ($records as $record) {
                $DB->delete_records("officehours_notification", ["waitlistid" => $record->id]);
                $DB->delete_records("officehours_waitlist", ["id" => $record->id]);
            }
        }
        foreach (["officehours_notification", "officehours_calendar"] as $table) {
            $DB->delete_records_select($table, "officehoursid = :officeid AND userid " . $insql, $params);
        }
        // Keep appointments belonging to other students, but eliminate identifying staff linkage.
        foreach (["officehours_availability", "officehours_slot", "officehours_exception"] as $table) {
            $DB->execute("UPDATE {" . $table . "} SET teacherid = 0 WHERE officehoursid = :officeid AND teacherid " . $insql,
                $params);
        }
    }

    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id("officehours", $context->instanceid);
        if (!$cm) {
            return;
        }
        $rows = $DB->get_records("officehours_booking", ["officehoursid" => $cm->instance]);
        foreach ($rows as $record) {
            \mod_officehours\local\calendar_service::remove((int)$record->id);
        }
        foreach (["calendar", "notification", "waitlist", "booking", "exception", "slot", "availability"] as $table) {
            $DB->delete_records("officehours_" . $table, ["officehoursid" => $cm->instance]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id("officehours", $context->instanceid);
                if ($cm) {
                    self::erase((int)$cm->instance, [(int)$contextlist->get_user()->id]);
                }
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id("officehours", $context->instanceid);
            if ($cm) {
                self::erase((int)$cm->instance, $userlist->get_userids());
            }
        }
    }
}
