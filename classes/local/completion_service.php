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
 * Completion API integration.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

/**
 * Derive completion from persisted confirmed and completed appointments.
 */
final class completion_service {
    /**
     * complete in Office Hours.
     */
    public static function complete(int $officehoursid, int $userid, bool $type = false): bool {
        global $DB;
        $activity = $DB->get_record("officehours", ["id" => $officehoursid], "*", MUST_EXIST);
        $counts = $DB->get_records_sql("SELECT state, COUNT(id) AS total FROM {officehours_booking}
            WHERE officehoursid = :oid AND userid = :uid GROUP BY state",
            ["oid" => $officehoursid, "uid" => $userid]);
        $confirmed = (int)($counts[booking_service::CONFIRMED]->total ?? 0);
        $completed = (int)($counts[booking_service::COMPLETED]->total ?? 0);
        $checks = [];
        if ($activity->completionbooked) {
            $checks[] = ($confirmed + $completed) >= 1;
        }
        if ($activity->completionattended) {
            $checks[] = $completed >= 1;
        }
        if ($activity->completioncount) {
            $checks[] = $completed >= $activity->completioncount;
        }
        if (!$checks) {
            return $type;
        }
        return !in_array(false, $checks, true);
    }

    /**
     * update in Office Hours.
     */
    public static function update(int $officehoursid, int $userid): void {
        $cm = get_coursemodule_from_instance("officehours", $officehoursid);
        if (!$cm || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
            return;
        }
        $info = new \completion_info(get_course($cm->course));
        $info->update_state($cm, COMPLETION_UNKNOWN, $userid);
    }
}
