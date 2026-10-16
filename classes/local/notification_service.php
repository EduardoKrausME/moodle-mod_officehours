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
 * Idempotent notification outbox using Moodle Message API.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

use core\message\message;
use core\lock\lock_config;

/**
 * Notifications are first persisted, then delivered by cron.
 */
final class notification_service {
    /**
     * queue in Office Hours.
     */
    private static function queue(int $officehoursid, int $bookingid, int $waitlistid,
            int $userid, string $eventkey): void {
        global $DB;
        if (!$DB->record_exists("officehours_notification", ["eventkey" => $eventkey])) {
            $DB->insert_record("officehours_notification", (object)[
                "officehoursid" => $officehoursid, "bookingid" => $bookingid,
                "waitlistid" => $waitlistid, "userid" => $userid,
                "eventkey" => $eventkey, "created" => time(), "sent" => 0,
            ]);
        }
    }

    /**
     * queue booking in Office Hours.
     */
    public static function queue_booking(int $bookingid, string $action): void {
        global $DB;
        $booking = $DB->get_record("officehours_booking", ["id" => $bookingid], "*", MUST_EXIST);
        $slot = $DB->get_record("officehours_slot", ["id" => $booking->slotid], "*", MUST_EXIST);
        foreach (array_unique([(int)$booking->userid, (int)$slot->teacherid]) as $userid) {
            self::queue((int)$booking->officehoursid, $bookingid, 0, $userid,
                "booking:" . $bookingid . ":" . $action . ":" . $booking->modified . ":" . $userid);
        }
    }

    /**
     * queue offer in Office Hours.
     */
    public static function queue_offer(int $offerid): void {
        global $DB;
        $entry = $DB->get_record("officehours_waitlist", ["id" => $offerid], "*", MUST_EXIST);
        self::queue((int)$entry->officehoursid, 0, $offerid, (int)$entry->userid,
            "offer:" . $offerid . ":" . $entry->offereduntil);
    }

    /**
     * queue reminders in Office Hours.
     */
    public static function queue_reminders(): void {
        global $DB;
        $sql = "SELECT b.* FROM {officehours_booking} b JOIN {officehours_slot} s ON s.id = b.slotid
                  JOIN {officehours} o ON o.id = b.officehoursid
                  WHERE b.state = :confirmed AND b.remindersent = 0 AND o.reminderminutes > 0
                    AND s.starttime > :now AND s.starttime <= :until";
        $now = time();
        $bookings = $DB->get_records_sql($sql, ["confirmed" => "confirmed", "now" => $now,
            "until" => $now + 1440 * MINSECS]);
        foreach ($bookings as $booking) {
            $slot = $DB->get_record("officehours_slot", ["id" => $booking->slotid]);
            $activity = $DB->get_record("officehours", ["id" => $booking->officehoursid]);
            if ($slot && $activity && $slot->starttime - $now <= $activity->reminderminutes * 60) {
                self::queue((int)$booking->officehoursid, (int)$booking->id, 0, (int)$booking->userid,
                    "reminder:" . $booking->id . ":" . $slot->starttime);
                $DB->set_field("officehours_booking", "remindersent", 1, ["id" => $booking->id]);
            }
        }
    }

    /**
     * flush in Office Hours.
     */
    public static function flush(): void {
        global $DB;
        $pending = $DB->get_records("officehours_notification", ["sent" => 0], "id ASC", "*", 0, 100);
        foreach ($pending as $record) {
            $lock = lock_config::get_lock_factory("mod_officehours")->get_lock("notification_" . $record->id, 2);
            if (!$lock) {
                continue;
            }
            try {
                if ($DB->get_field("officehours_notification", "sent", ["id" => $record->id]) != 0) {
                    continue;
                }
                // Claim before sending to prevent duplicate sends by another cron worker.
                $DB->set_field("officehours_notification", "sent", time(), ["id" => $record->id]);
                $person = $DB->get_record("user", ["id" => $record->userid], "*", MUST_EXIST);
                $activity = $DB->get_record("officehours", ["id" => $record->officehoursid], "*", MUST_EXIST);
                $cm = get_coursemodule_from_instance("officehours", $activity->id);
                $payload = new message();
                $payload->component = "mod_officehours";
                $payload->name = $record->waitlistid ? "offer" :
                    (str_starts_with($record->eventkey, "reminder:") ? "reminder" : "booking");
                $payload->userfrom = \core_user::get_noreply_user();
                $payload->userto = $person;
                $payload->subject = get_string("notificationtitle", "officehours", $activity->name);
                $payload->fullmessage = get_string("notificationbody", "officehours");
                $payload->fullmessageformat = FORMAT_PLAIN;
                $payload->fullmessagehtml = "";
                $payload->smallmessage = $payload->fullmessage;
                $payload->notification = 1;
                $payload->contexturl = (new \moodle_url("/mod/officehours/view.php", ["id" => $cm->id]))->out(false);
                $payload->contexturlname = $activity->name;
                message_send($payload);
            } catch (\Throwable $exception) {
                // Do not automatically retry ambiguous send failures: a provider may already have delivered it.
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            } finally {
                $lock->release();
            }
        }
    }
}
