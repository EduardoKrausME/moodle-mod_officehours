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
 * Track activity completion based on guidance requests.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_officehours\local;

use completion_info;

/**
 * Completion for private learning-support requests rather than attendance.
 */
final class completion_service {
    /**
     * Determine whether a learner has satisfied configured completion conditions.
     */
    public static function complete(int $officehoursid, int $userid, bool $type = false): bool {
        global $DB;
        $activity = $DB->get_record('officehours', ['id' => $officehoursid], '*', MUST_EXIST);
        $created = $DB->count_records('officehours_request',
            ['officehoursid' => $officehoursid, 'userid' => $userid]);
        $resolved = $DB->count_records_select('officehours_request',
            "officehoursid = :oid AND userid = :uid AND state IN ('resolved', 'closed')",
            ['oid' => $officehoursid, 'uid' => $userid]);
        $checks = [];
        if ($activity->completionrequested) {
            $checks[] = $created > 0;
        }
        if ($activity->completionresolved) {
            $checks[] = $resolved > 0;
        }
        if ($activity->completiontarget) {
            $checks[] = $resolved >= (int)$activity->completiontarget;
        }
        return $checks ? !in_array(false, $checks, true) : $type;
    }

    /**
     * Refresh the completion status for a learner.
     */
    public static function update(int $officehoursid, int $userid): void {
        $cm = get_coursemodule_from_instance('officehours', $officehoursid);
        if (!$cm || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
            return;
        }
        $info = new completion_info(get_course($cm->course));
        $info->update_state($cm, COMPLETION_UNKNOWN, $userid);
    }
}
