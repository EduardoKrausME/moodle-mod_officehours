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
 * PHPUnit and Behat test data generator.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Moodle guard uses mandatory double quotes.
defined("MOODLE_INTERNAL") || die();

/**
 * Generate Office Hours modules for automated tests.
 */
class mod_officehours_generator extends testing_module_generator {
    /**
     * Create a module instance with valid default data.
     *
     * @param stdClass|array|null $record Activity data.
     * @param array|null $options Optional settings.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object)(array)$record;
        $record->name = $record->name ?? "Office Hours";
        $record->intro = $record->intro ?? "";
        $record->introformat = $record->introformat ?? FORMAT_HTML;
        return parent::create_instance($record, (array)$options);
    }
}
