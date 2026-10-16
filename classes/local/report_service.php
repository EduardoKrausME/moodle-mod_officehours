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
 * Teacher dashboard, CSV, and student schedule data.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

/** Restrict reporting to authorized bookings. */
final class report_service {
    public static function bookings(\stdClass $cm, int $from, int $to, int $studentid = 0): array {
        global $DB, $USER;
        access_manager::capability($cm, "viewbookings");
        $params = ["officeid" => $cm->instance, "start" => $from, "end" => $to];
        $sql = "SELECT b.*, s.starttime, s.endtime, s.teacherid, u.firstname, u.lastname
                  FROM {officehours_booking} b JOIN {officehours_slot} s ON s.id = b.slotid
                  JOIN {user} u ON u.id = b.userid
                 WHERE b.officehoursid = :officeid AND s.starttime >= :start AND s.starttime < :end";
        if ($studentid) {
            $sql .= " AND b.userid = :studentid";
            $params["studentid"] = $studentid;
        }
        $sql .= " ORDER BY s.starttime ASC, b.id ASC";
        $rows = $DB->get_records_sql($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            if (access_manager::visible_student($cm, (int)$row->userid, (int)$USER->id)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    public static function export(\stdClass $cm, int $from, int $to, int $studentid = 0): void {
        access_manager::capability($cm, "reports");
        $rows = self::bookings($cm, $from, $to, $studentid);
        $filename = "officehours-" . $cm->instance . ".csv";
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        $output = fopen("php://output", "w");
        fputcsv($output, ["Booking ID", "Student ID", "First name", "Last name", "Start UTC",
            "End UTC", "Status", "Subject", "Outcome"]);
        foreach ($rows as $booking) {
            $cells = [(int)$booking->id, (int)$booking->userid, $booking->firstname,
                $booking->lastname, gmdate("c", $booking->starttime), gmdate("c", $booking->endtime),
                $booking->state, $booking->subject, $booking->outcome];
            foreach ($cells as &$value) {
                if (is_string($value) && preg_match("/^[=+@\\-\\t\\r]/", $value)) {
                    $value = "'" . $value;
                }
            }
            unset($value);
            fputcsv($output, $cells);
        }
        fclose($output);
        exit;
    }
}
