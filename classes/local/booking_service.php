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
 * Reservation domain operations and state transitions.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

/**
 * Transactional booking state machine.
 */
final class booking_service {
    /**
     * PENDING in Office Hours.
     */
    public const PENDING = "pending";
    /**
     * CONFIRMED in Office Hours.
     */
    public const CONFIRMED = "confirmed";
    /**
     * CANCELLED in Office Hours.
     */
    public const CANCELLED = "cancelled";
    /**
     * COMPLETED in Office Hours.
     */
    public const COMPLETED = "completed";
    /**
     * NOSHOW in Office Hours.
     */
    public const NOSHOW = "no-show";
    /**
     * REJECTED in Office Hours.
     */
    public const REJECTED = "rejected";

    /**
     * ALLOWED in Office Hours.
     */
    private const ALLOWED = [
        self::PENDING => [self::CONFIRMED, self::REJECTED, self::CANCELLED],
        self::CONFIRMED => [self::CANCELLED, self::COMPLETED, self::NOSHOW],
        self::CANCELLED => [],
        self::COMPLETED => [],
        self::NOSHOW => [],
        self::REJECTED => [],
    ];

    /**
     * validate window in Office Hours.
     */
    private static function validate_window(\stdClass $activity, \stdClass $slot, bool $cancel = false): void {
        $now = time();
        if ($cancel) {
            if ($slot->starttime - $now < (int)$activity->cancelnotice * 60) {
                throw new \moodle_exception("cancellationclosed", "officehours");
            }
            return;
        }
        if ($slot->disabled || $slot->starttime < $now + (int)$activity->minnotice * 60 ||
                $slot->starttime > $now + (int)$activity->maxnotice * 60 ||
                ($activity->timeopen && $slot->starttime < $activity->timeopen) ||
                ($activity->timeclose && $slot->endtime > $activity->timeclose)) {
            throw new \moodle_exception("bookingclosed", "officehours");
        }
    }

    /**
     * validate student in Office Hours.
     */
    private static function validate_student(\stdClass $cm, int $userid): void {
        global $USER;
        if ((int)$USER->id !== $userid) {
            throw new \moodle_exception("nopermissions", "error", "", "Cannot book for another user");
        }
        access_manager::capability($cm, "book");
        if (!is_enrolled(\context_module::instance($cm->id), $userid)) {
            throw new \moodle_exception("notenrolled", "error");
        }
    }

    /**
     * unique in Office Hours.
     */
    private static function unique(int $slotid, int $userid, int $excludedid = 0): void {
        global $DB;
        if ($DB->record_exists_select("officehours_booking",
                "slotid = :slot AND userid = :student AND state IN ('pending', 'confirmed') AND id <> :exclude",
                ["slot" => $slotid, "student" => $userid, "exclude" => $excludedid])) {
            throw new \moodle_exception("duplicatebooking", "officehours");
        }
    }

    /**
     * limit in Office Hours.
     */
    private static function limit(\stdClass $activity, int $userid, int $excludedid = 0): void {
        global $DB;
        $count = $DB->count_records_select("officehours_booking",
            "officehoursid = :office AND userid = :student AND id <> :exclude AND state IN ('pending', 'confirmed')",
            ["office" => $activity->id, "student" => $userid, "exclude" => $excludedid]);
        if ($count >= $activity->maxbookings) {
            throw new \moodle_exception("bookinglimit", "officehours");
        }
    }

    /**
     * The offered capacity is already reserved, and is excluded when converted.
     */
    public static function book(int $officehoursid, int $slotid, int $userid,
            string $subject = "", string $notes = "", int $offerid = 0): int {
        global $DB;
        $cm = get_coursemodule_from_instance("officehours", $officehoursid, 0, false, MUST_EXIST);
        self::validate_student($cm, $userid);
        $lock = slot_service::acquire($slotid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $activity = $DB->get_record("officehours", ["id" => $officehoursid], "*", MUST_EXIST);
            $slot = $DB->get_record("officehours_slot", ["id" => $slotid, "officehoursid" => $officehoursid], "*", MUST_EXIST);
            self::validate_window($activity, $slot);
            self::unique($slotid, $userid);
            self::limit($activity, $userid);
            if (!$activity->allowsubject) {
                $subject = "";
            }
            if ($offerid) {
                $offer = $DB->get_record("officehours_waitlist", ["id" => $offerid, "slotid" => $slotid,
                    "userid" => $userid, "status" => "offered"], "*", MUST_EXIST);
                if ($offer->offereduntil <= time()) {
                    throw new \moodle_exception("offerexpired", "officehours");
                }
                $DB->set_field("officehours_waitlist", "status", "accepted", ["id" => $offerid]);
            }
            if (slot_service::occupied($slotid) >= $slot->capacity) {
                // Exactly one active offered seat belongs to this student, otherwise the slot is full.
                if (!$offerid || slot_service::occupied($slotid) > $slot->capacity) {
                    throw new \moodle_exception("slotfull", "officehours");
                }
            }
            $state = $activity->approvalrequired ? self::PENDING : self::CONFIRMED;
            $id = $DB->insert_record("officehours_booking", (object)[
                "officehoursid" => $officehoursid, "slotid" => $slotid,
                "userid" => $userid, "state" => $state,
                "subject" => clean_param($subject, PARAM_TEXT), "notes" => clean_param($notes, PARAM_TEXT),
                "created" => time(), "modified" => time(), "remindersent" => 0,
            ]);
            notification_service::queue_booking($id, "created");
            $transaction->allow_commit();
            calendar_service::sync($id);
            completion_service::update($officehoursid, $userid);
            return $id;
        } finally {
            $lock->release();
        }
    }

    /**
     * change state in Office Hours.
     */
    public static function change_state(int $bookingid, string $state, int $actorid,
            string $internalnotes = "", string $outcome = ""): void {
        global $DB;
        global $USER;
        if ((int)$USER->id !== $actorid) {
            throw new \moodle_exception("nopermissions", "error");
        }
        $old = $DB->get_record("officehours_booking", ["id" => $bookingid], "*", MUST_EXIST);
        $lock = slot_service::acquire((int)$old->slotid);
        try {
            $transaction = $DB->start_delegated_transaction();
            $booking = $DB->get_record("officehours_booking", ["id" => $bookingid], "*", MUST_EXIST);
            if ((int)$booking->slotid !== (int)$old->slotid) {
                throw new \moodle_exception("staleoperation", "officehours");
            }
            if (!in_array($state, self::ALLOWED[$booking->state] ?? [], true)) {
                throw new \moodle_exception("invalidtransition", "officehours");
            }
            $cm = get_coursemodule_from_instance("officehours", $booking->officehoursid, 0, false, MUST_EXIST);
            $activity = $DB->get_record("officehours", ["id" => $booking->officehoursid], "*", MUST_EXIST);
            $slot = $DB->get_record("officehours_slot", ["id" => $booking->slotid], "*", MUST_EXIST);
            if ($state === self::CANCELLED && $actorid === (int)$booking->userid) {
                access_manager::capability($cm, "manageown");
                self::validate_window($activity, $slot, true);
            } else {
                access_manager::teacher((int)$booking->officehoursid, (int)$slot->teacherid,
                    in_array($state, [self::COMPLETED, self::NOSHOW], true) ? "attendance" : "approve");
                if (!access_manager::visible_student($cm, (int)$booking->userid, $actorid)) {
                    throw new \moodle_exception("nopermissions", "error");
                }
            }
            if ($state === self::COMPLETED || $state === self::NOSHOW) {
                if ($slot->starttime > time()) {
                    throw new \moodle_exception("notstarted", "officehours");
                }
                $booking->internalnotes = clean_param($internalnotes, PARAM_TEXT);
                $booking->outcome = clean_param($outcome, PARAM_TEXT);
            }
            $booking->state = $state;
            $booking->modified = time();
            $DB->update_record("officehours_booking", $booking);
            notification_service::queue_booking($bookingid, $state);
            if (in_array($state, [self::CANCELLED, self::REJECTED], true)) {
                waiting_list_service::promote_locked((int)$slot->id);
            }
            $transaction->allow_commit();
            calendar_service::sync($bookingid);
            completion_service::update((int)$booking->officehoursid, (int)$booking->userid);
        } finally {
            $lock->release();
        }
    }

    /**
     * Atomic seat transfer across slots using stable lock ordering.
     */
    public static function reschedule(int $bookingid, int $newslotid, int $userid): void {
        global $DB;
        $old = $DB->get_record("officehours_booking", ["id" => $bookingid], "*", MUST_EXIST);
        $ids = array_unique([(int)$old->slotid, $newslotid]);
        sort($ids, SORT_NUMERIC);
        $locks = [];
        try {
            foreach ($ids as $slotid) {
                $locks[] = slot_service::acquire($slotid);
            }
            $transaction = $DB->start_delegated_transaction();
            $booking = $DB->get_record("officehours_booking", ["id" => $bookingid], "*", MUST_EXIST);
            $cm = get_coursemodule_from_instance("officehours", $booking->officehoursid, 0, false, MUST_EXIST);
            self::validate_student($cm, $userid);
            $activity = $DB->get_record("officehours", ["id" => $booking->officehoursid], "*", MUST_EXIST);
            if (!$activity->allowreschedule || $booking->userid != $userid ||
                    !in_array($booking->state, [self::PENDING, self::CONFIRMED], true)) {
                throw new \moodle_exception("rescheduleclosed", "officehours");
            }
            if ($booking->slotid !== $old->slotid) {
                throw new \moodle_exception("staleoperation", "officehours");
            }
            $oldslot = $DB->get_record("officehours_slot", ["id" => $booking->slotid], "*", MUST_EXIST);
            self::validate_window($activity, $oldslot, true);
            if ($newslotid === (int)$oldslot->id) {
                throw new \moodle_exception("staleoperation", "officehours");
            }
            $target = $DB->get_record("officehours_slot", ["id" => $newslotid,
                "officehoursid" => $booking->officehoursid], "*", MUST_EXIST);
            self::validate_window($activity, $target);
            self::unique($newslotid, $userid, $bookingid);
            if (!slot_service::open($newslotid)) {
                throw new \moodle_exception("slotfull", "officehours");
            }
            $booking->slotid = $newslotid;
            $booking->modified = time();
            $booking->remindersent = 0;
            $DB->update_record("officehours_booking", $booking);
            notification_service::queue_booking($bookingid, "rescheduled");
            waiting_list_service::promote_locked((int)$oldslot->id);
            $transaction->allow_commit();
            calendar_service::sync($bookingid);
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }
}
