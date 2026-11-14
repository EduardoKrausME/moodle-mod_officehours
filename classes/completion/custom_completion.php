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
 * Expose academic guidance completion rules.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_officehours\completion;

use coding_exception;
use core_completion\activity_custom_completion;

/**
 * Custom completion based on consultations submitted and resolved.
 */
final class custom_completion extends activity_custom_completion {
    /**
     * List all available custom completion rules.
     */
    public static function get_defined_custom_rules(): array {
        return ['completionrequested', 'completionresolved', 'completiontarget'];
    }

    /**
     * Provide the descriptions of custom completion rules.
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $activity = $DB->get_record('officehours', ['id' => $this->cm->instance], '*', MUST_EXIST);
        return [
            'completionrequested' => get_string('completionrequested', 'officehours'),
            'completionresolved' => get_string('completionresolved', 'officehours'),
            'completiontarget' => get_string('completiontarget', 'officehours', $activity->completiontarget),
        ];
    }

    /**
     * Evaluate the current state of one completion rule.
     */
    public function get_state(string $rule): int {
        global $DB;
        if (!in_array($rule, self::get_defined_custom_rules(), true)) {
            throw new coding_exception('Unknown completion condition');
        }
        $activity = $DB->get_record('officehours', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $created = $DB->count_records('officehours_request',
            ['officehoursid' => $activity->id, 'userid' => $this->userid]);
        $resolved = $DB->count_records_select('officehours_request',
            "officehoursid = :oid AND userid = :uid AND state IN ('resolved', 'closed')",
            ['oid' => $activity->id, 'uid' => $this->userid]);
        $passed = match ($rule) {
            'completionrequested' => $created > 0,
            'completionresolved' => $resolved > 0,
            'completiontarget' => $resolved >= (int)$activity->completiontarget,
        };
        return $passed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Return the display order of completion rules.
     */
    public function get_sort_order(): array {
        return self::get_defined_custom_rules();
    }
}
