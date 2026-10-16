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
 * Moodle custom completion rules.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\completion;

use core_completion\activity_custom_completion;

/** Expose the booking and participation criteria to the modern completion API. */
final class custom_completion extends activity_custom_completion {
    public static function get_defined_custom_rules(): array {
        return ["completionbooked", "completionattended", "completioncount"];
    }

    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record("officehours", ["id" => $this->cm->instance], "*", MUST_EXIST);
        return [
            "completionbooked" => get_string("completionbooked", "officehours"),
            "completionattended" => get_string("completionattended", "officehours"),
            "completioncount" => get_string("completioncount", "officehours", $activity->completioncount),
        ];
    }

    public function get_state(string $rule): int {
        global $DB;
        if (!in_array($rule, self::get_defined_custom_rules(), true)) {
            throw new \coding_exception("Unknown completion condition");
        }
        $activity = $DB->get_record("officehours", ["id" => $this->cm->instance], "*", MUST_EXIST);
        $confirmed = $DB->count_records_select("officehours_booking",
            "officehoursid = :officeid AND userid = :userid AND state IN ('confirmed', 'completed')",
            ["officeid" => $activity->id, "userid" => $this->userid]);
        $completed = $DB->count_records("officehours_booking", ["officehoursid" => $activity->id,
            "userid" => $this->userid, "state" => "completed"]);
        $passed = match ($rule) {
            "completionbooked" => $confirmed > 0,
            "completionattended" => $completed > 0,
            "completioncount" => $completed >= $activity->completioncount,
        };
        return $passed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    public function get_sort_order(): array {
        return self::get_defined_custom_rules();
    }
}
