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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Manage teacher availability rules and exceptions.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

/**
 * Server-side availability mutations, preserving booked history.
 */
final class availability_service {
    /**
     * save in Office Hours.
     */
    public static function save(int $officehoursid, int $teacherid, array $data, int $id = 0): int {
        global $DB;
        access_manager::teacher($officehoursid, $teacherid, "createavailability");
        if ($data["startminute"] < 0 || $data["endminute"] > 1440 || $data["startminute"] >= $data["endminute"] ||
                $data["enddate"] < $data["startdate"] || !in_array($data["kind"], ["single", "weekly"], true)) {
            throw new \invalid_parameter_exception("Invalid availability range");
        }
        new \DateTimeZone($data["timezone"]);
        if ($id) {
            $original = $DB->get_record("officehours_availability", ["id" => $id, "officehoursid" => $officehoursid], "*", MUST_EXIST);
            if ((int)$original->teacherid !== $teacherid) {
                throw new \invalid_parameter_exception("Ownership mismatch");
            }
            // Existing slots remain unchanged: future booked appointments are never silently moved.
            $DB->update_record("officehours_availability", (object)array_merge($data, [
                "id" => $id, "timemodified" => time(),
            ]));
            return $id;
        }
        return $DB->insert_record("officehours_availability", (object)array_merge($data, [
            "officehoursid" => $officehoursid, "teacherid" => $teacherid,
            "timecreated" => time(), "timemodified" => time(), "enabled" => 1,
        ]));
    }

    /**
     * block in Office Hours.
     */
    public static function block(int $officehoursid, int $teacherid, int $from, int $to, string $reason = ""): int {
        global $DB;
        access_manager::teacher($officehoursid, $teacherid, "createavailability");
        if ($from >= $to || $to <= time()) {
            throw new \invalid_parameter_exception("Invalid block window");
        }
        $expandlock = \core\lock\lock_config::get_lock_factory("mod_officehours")
            ->get_lock("expand_" . $officehoursid, 20);
        if (!$expandlock) {
            throw new \moodle_exception("lockfailed", "officehours");
        }
        $locks = [];
        try {
            $slots = $DB->get_records_select("officehours_slot",
                "officehoursid = :officeid AND teacherid = :teacherid AND starttime < :totime AND endtime > :fromtime",
                ["officeid" => $officehoursid, "teacherid" => $teacherid, "totime" => $to, "fromtime" => $from],
                "id ASC");
            foreach ($slots as $slot) {
                $locks[] = slot_service::acquire((int)$slot->id);
            }
            $transaction = $DB->start_delegated_transaction();
            foreach ($slots as $slot) {
                if (slot_service::occupied($slot->id)) {
                    throw new \moodle_exception("blockbooked", "officehours");
                }
                $DB->set_field("officehours_slot", "disabled", 1, ["id" => $slot->id]);
            }
            $id = $DB->insert_record("officehours_exception", (object)[
                "officehoursid" => $officehoursid, "teacherid" => $teacherid,
                "starttime" => $from, "endtime" => $to, "reason" => $reason,
            ]);
            $transaction->allow_commit();
            return $id;
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
            $expandlock->release();
        }
    }

    /**
     * delete in Office Hours.
     */
    public static function delete(int $officehoursid, int $teacherid, int $id): void {
        global $DB;
        access_manager::teacher($officehoursid, $teacherid, "createavailability");
        $rule = $DB->get_record("officehours_availability", ["id" => $id,
            "officehoursid" => $officehoursid, "teacherid" => $teacherid], "*", MUST_EXIST);
        $expandlock = \core\lock\lock_config::get_lock_factory("mod_officehours")
            ->get_lock("expand_" . $officehoursid, 20);
        if (!$expandlock) {
            throw new \moodle_exception("lockfailed", "officehours");
        }
        try {
            $slots = $DB->get_records_select("officehours_slot", "availabilityid = :rule AND starttime > :now",
                ["rule" => $id, "now" => time()], "id ASC");
            foreach ($slots as $slot) {
                $lock = slot_service::acquire((int)$slot->id);
                try {
                    if (!slot_service::occupied((int)$slot->id)) {
                        $DB->set_field("officehours_slot", "disabled", 1, ["id" => $slot->id]);
                    }
                } finally {
                    $lock->release();
                }
            }
            $DB->set_field("officehours_availability", "enabled", 0, ["id" => $rule->id]);
        } finally {
            $expandlock->release();
        }
    }
}
