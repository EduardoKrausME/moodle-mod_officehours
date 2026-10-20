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
 * Waiting list and timed offers.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

/**
 * Every promotion is protected by the slot lock held by its caller.
 */
final class waiting_list_service {
    /**
     * join in Office Hours.
     */
    public static function join(int $slotid, int $userid): int {
        global $DB;
        $slot = $DB->get_record("officehours_slot", ["id" => $slotid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("officehours", $slot->officehoursid, 0, false, MUST_EXIST);
        access_manager::capability($cm, "book");
        if (!is_enrolled(\context_module::instance($cm->id), $userid)) {
            throw new \moodle_exception("notenrolled", "error");
        }
        global $USER;
        if ($USER->id != $userid) {
            throw new \moodle_exception("nopermissions", "error");
        }
        $activity = $DB->get_record("officehours", ["id" => $slot->officehoursid], "*", MUST_EXIST);
        if (!$activity->waitlistenabled || $slot->starttime <= time()) {
            throw new \moodle_exception("waitlistclosed", "officehours");
        }
        $lock = slot_service::acquire($slotid);
        try {
            $transaction = $DB->start_delegated_transaction();
            if (slot_service::open($slotid)) {
                throw new \moodle_exception("slotavailable", "officehours");
            }
            if ($DB->record_exists_select("officehours_booking",
                    "slotid = :slot AND userid = :student AND state IN ('pending', 'confirmed')",
                    ["slot" => $slotid, "student" => $userid]) ||
                    $DB->record_exists_select("officehours_waitlist",
                    "slotid = :slot2 AND userid = :student2 AND status IN ('waiting', 'offered')",
                    ["slot2" => $slotid, "student2" => $userid])) {
                throw new \moodle_exception("duplicatebooking", "officehours");
            }
            $id = $DB->insert_record("officehours_waitlist", (object)[
                "officehoursid" => $slot->officehoursid, "slotid" => $slotid, "userid" => $userid,
                "status" => "waiting", "created" => time(), "modified" => time(),
            ]);
            $transaction->allow_commit();
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * promote locked in Office Hours.
     */
    public static function promote_locked(int $slotid): void {
        global $DB;
        $slot = $DB->get_record("officehours_slot", ["id" => $slotid], "*", MUST_EXIST);
        if ($slot->disabled || $slot->starttime <= time()) {
            return;
        }
        $activity = $DB->get_record("officehours", ["id" => $slot->officehoursid], "*", MUST_EXIST);
        while (slot_service::occupied($slotid) < (int)$slot->capacity) {
            $entries = $DB->get_records("officehours_waitlist",
                ["slotid" => $slotid, "status" => "waiting"], "created ASC, id ASC", "*", 0, 1);
            if (!$entries) {
                break;
            }
            $entry = reset($entries);
            $count = $DB->count_records_select("officehours_booking",
                "officehoursid = :oid AND userid = :uid AND state IN ('pending', 'confirmed')",
                ["oid" => $slot->officehoursid, "uid" => $entry->userid]);
            if ($count >= $activity->maxbookings) {
                $DB->set_field("officehours_waitlist", "status", "ineligible", ["id" => $entry->id]);
                continue;
            }
            $entry->status = "offered";
            $entry->offereduntil = min($slot->starttime, time() + $activity->offerexpires * 60);
            $entry->modified = time();
            $DB->update_record("officehours_waitlist", $entry);
            notification_service::queue_offer((int)$entry->id);
        }
    }

    /**
     * expire and promote in Office Hours.
     */
    public static function expire_and_promote(): void {
        global $DB;
        $sql = "SELECT DISTINCT slotid FROM {officehours_waitlist} WHERE status = :status AND offereduntil <= :now";
        $slots = $DB->get_records_sql($sql, ["status" => "offered", "now" => time()]);
        foreach ($slots as $row) {
            $lock = slot_service::acquire((int)$row->slotid);
            try {
                $transaction = $DB->start_delegated_transaction();
                $DB->execute("UPDATE {officehours_waitlist} SET status = :expired, modified = :changed
                    WHERE slotid = :slotid AND status = :offered AND offereduntil <= :now",
                    ["expired" => "expired", "changed" => time(), "slotid" => $row->slotid,
                        "offered" => "offered", "now" => time()]);
                self::promote_locked((int)$row->slotid);
                $transaction->allow_commit();
            } finally {
                $lock->release();
            }
        }
    }
}
