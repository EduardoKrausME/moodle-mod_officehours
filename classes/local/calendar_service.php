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
 * Private user calendar events.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

/**
 * One private user event per booking participant.
 */
final class calendar_service {
    /**
     * sync in Office Hours.
     */
    public static function sync(int $bookingid): void {
        global $DB;
        $booking = $DB->get_record("officehours_booking", ["id" => $bookingid]);
        if (!$booking) {
            return;
        }
        if ($booking->state !== booking_service::CONFIRMED) {
            self::remove($bookingid);
            return;
        }
        $slot = $DB->get_record("officehours_slot", ["id" => $booking->slotid], "*", MUST_EXIST);
        $activity = $DB->get_record("officehours", ["id" => $booking->officehoursid], "*", MUST_EXIST);
        $cm = get_coursemodule_from_instance("officehours", $booking->officehoursid, 0, false, MUST_EXIST);
        $recipients = array_unique([(int)$booking->userid, (int)$slot->teacherid]);
        foreach ($recipients as $userid) {
            $params = ["bookingid" => $bookingid, "userid" => $userid];
            $previous = $DB->get_record("officehours_calendar", $params);
            $data = (object)[
                "name" => get_string("calendartitle", "officehours", $activity->name),
                "description" => get_string("calendardescription", "officehours"),
                "format" => FORMAT_PLAIN, "courseid" => 0, "userid" => $userid,
                "modulename" => "officehours", "instance" => $activity->id,
                "eventtype" => "user", "timestart" => $slot->starttime,
                "timeduration" => $slot->endtime - $slot->starttime,
                "visible" => 1,
            ];
            if ($previous) {
                \calendar_event::load($previous->eventid)->update($data, false);
            } else {
                $event = \calendar_event::create($data, false);
                $DB->insert_record("officehours_calendar", (object)[
                    "officehoursid" => $activity->id, "bookingid" => $bookingid,
                    "userid" => $userid, "eventid" => $event->id,
                ]);
            }
        }
        $old = $DB->get_records("officehours_calendar", ["bookingid" => $bookingid]);
        foreach ($old as $row) {
            if (!in_array((int)$row->userid, $recipients, true)) {
                \calendar_event::load($row->eventid)->delete();
                $DB->delete_records("officehours_calendar", ["id" => $row->id]);
            }
        }
    }

    /**
     * remove in Office Hours.
     */
    public static function remove(int $bookingid): void {
        global $DB;
        foreach ($DB->get_records("officehours_calendar", ["bookingid" => $bookingid]) as $row) {
            try {
                \calendar_event::load($row->eventid)->delete();
            } catch (\Throwable $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
            $DB->delete_records("officehours_calendar", ["id" => $row->id]);
        }
    }
}
