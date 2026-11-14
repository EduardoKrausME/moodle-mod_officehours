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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
use core_privacy\local\request\core_user_data_provider;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_officehours\local\calendar_service;

/**
 * Privacy provider for student and staff scheduling data.
 */
final class provider implements
        \core_privacy\local\metadata\provider,
        core_user_data_provider,
        core_userlist_provider {

    /**
     * get metadata in Office Hours.
     */
    public static function get_metadata(collection $collection): collection {
        $tables = [
            'officehours_request' => ['userid' => 'privacy:userid', 'teacherid' => 'privacy:teacher',
                'subject' => 'privacy:request', 'description' => 'privacy:request'],
            'officehours_message' => ['userid' => 'privacy:userid', 'body' => 'privacy:request'],
            'officehours_history' => ['userid' => 'privacy:userid'],
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

    /**
     * contexts in Office Hours.
     */
    private static function contexts(int $userid): array {
        global $DB;
        $ids = [];
        $sources = ["officehours_request" => "userid", "officehours_message" => "userid",
            "officehours_history" => "userid",
            "officehours_booking" => "userid", "officehours_waitlist" => "userid",
            "officehours_availability" => "teacherid", "officehours_slot" => "teacherid",
            "officehours_exception" => "teacherid", "officehours_notification" => "userid",
            "officehours_calendar" => "userid"];
        foreach ($sources as $table => $column) {
            $activities = $DB->get_fieldset_select($table, "officehoursid", $column . " = :uid", ["uid" => $userid]);
            $ids = array_merge($ids, $activities);
        }
        $ids = array_merge($ids, $DB->get_fieldset_select('officehours_request',
            'officehoursid', 'teacherid = :uid', ['uid' => $userid]));
        return array_unique(array_map("intval", $ids));
    }

    /**
     * get contexts for userid in Office Hours.
     */
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

    /**
     * get users in context in Office Hours.
     */
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
        foreach (["officehours_request" => "userid", "officehours_message" => "userid",
            "officehours_history" => "userid",
            "officehours_booking" => "userid", "officehours_waitlist" => "userid",
                "officehours_availability" => "teacherid", "officehours_slot" => "teacherid",
                "officehours_exception" => "teacherid", "officehours_notification" => "userid",
                "officehours_calendar" => "userid"] as $table => $field) {
            $users = array_merge($users, $DB->get_fieldset_select($table, $field,
                "officehoursid = :officeid", ["officeid" => $cm->instance]));
        }
        $users = array_merge($users, $DB->get_fieldset_select('officehours_request',
            'teacherid', 'officehoursid = :officeid', ['officeid' => $cm->instance]));
        $userlist->add_users(array_filter(array_unique(array_map("intval", $users))));
    }

    /**
     * export user data in Office Hours.
     */
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
            // A request owner may download the visible conversation, not just their own replies.
            $owned = $DB->get_records('officehours_request',
                ['officehoursid' => $cm->instance, 'userid' => $userid]);
            foreach ($owned as $request) {
                $messages = $DB->get_records('officehours_message',
                    ['officehoursid' => $cm->instance, 'requestid' => $request->id, 'internal' => 0]);
                $history = $DB->get_records('officehours_history',
                    ['officehoursid' => $cm->instance, 'requestid' => $request->id]);
                writer::with_context($context)->export_data(['consultation_' . $request->id],
                    (object)['request' => $request, 'messages' => array_values($messages),
                        'history' => array_values($history)]);
            }
            foreach (["officehours_request" => "userid", "officehours_message" => "userid",
            "officehours_history" => "userid",
            "officehours_booking" => "userid", "officehours_waitlist" => "userid",
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

    /**
     * erase in Office Hours.
     */
    private static function erase(int $instanceid, array $userids): void {
        global $DB;
        $userids = array_filter(array_unique(array_map("intval", $userids)));
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "privacy");
        $params["officeid"] = $instanceid;
        // Remove all conversation records when their owner requests erasure.
        $requests = $DB->get_records_select('officehours_request',
            'officehoursid = :officeid AND userid ' . $insql, $params);
        foreach ($requests as $request) {
            $DB->delete_records('officehours_message', ['requestid' => $request->id]);
            $DB->delete_records('officehours_history', ['requestid' => $request->id]);
            $DB->delete_records('officehours_request', ['id' => $request->id]);
            $DB->delete_records_select('officehours_notification',
                $DB->sql_like('eventkey', ':eventprefix'), ['eventprefix' => 'request:' . $request->id . ':%']);
        }
        foreach (['officehours_message', 'officehours_history'] as $table) {
            $DB->delete_records_select($table,
                'officehoursid = :officeid AND userid ' . $insql, $params);
        }
        $DB->execute('UPDATE {officehours_request} SET teacherid = 0
            WHERE officehoursid = :officeid AND teacherid ' . $insql, $params);
        $bookings = $DB->get_records_select("officehours_booking",
            "officehoursid = :officeid AND userid " . $insql, $params);
        foreach ($bookings as $booking) {
            calendar_service::remove((int)$booking->id);
            $DB->delete_records("officehours_notification", ["bookingid" => $booking->id]);
            $DB->delete_records("officehours_booking", ["id" => $booking->id]);
        }
        foreach ([$DB->get_records_select("officehours_waitlist",
                "officehoursid = :officeid AND userid " . $insql, $params)] as $records) {
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

    /**
     * delete data for all users in context in Office Hours.
     */
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
            calendar_service::remove((int)$record->id);
        }
        foreach (["message", "history", "request", "calendar", "notification", "waitlist", "booking", "exception", "slot", "availability"] as $table) {
            $DB->delete_records("officehours_" . $table, ["officehoursid" => $cm->instance]);
        }
    }

    /**
     * delete data for user in Office Hours.
     */
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

    /**
     * delete data for users in Office Hours.
     */
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
