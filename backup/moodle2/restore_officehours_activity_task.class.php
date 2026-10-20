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
 * Restore task registration.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Mandatory double-quoted guard.
defined("MOODLE_INTERNAL") || die();
require_once($CFG->dirroot . "/mod/officehours/backup/moodle2/restore_officehours_activity_structure_step.class.php");

/**
 * Restore officehours activity task for the Office Hours activity.
 */
class restore_officehours_activity_task extends restore_activity_task {
    /**
     * Define my settings for the Office Hours activity.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define my steps for the Office Hours activity.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_officehours_activity_structure_step("officehours_structure", "officehours.xml"));
    }

    /**
     * Define decode contents for the Office Hours activity.
     */
    public static function define_decode_contents(): array {
        return [];
    }

    /**
     * Define decode rules for the Office Hours activity.
     */
    public static function define_decode_rules(): array {
        return [];
    }
}
