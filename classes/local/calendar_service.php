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
 * Private user calendar events.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

use calendar_event;
use Throwable;

/** Remove historical user calendar events created by previous plugin versions. */
final class calendar_service {
    public static function remove(int $bookingid): void {
        global $DB;
        foreach ($DB->get_records("officehours_calendar", ["bookingid" => $bookingid]) as $row) {
            try {
                calendar_event::load($row->eventid)->delete();
            } catch (Throwable $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            }
            $DB->delete_records("officehours_calendar", ["id" => $row->id]);
        }
    }
}
