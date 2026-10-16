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
 * Form for weekly and exceptional availability.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\form;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Mandatory double-quoted guard.
defined("MOODLE_INTERNAL") || die();
require_once($CFG->libdir . "/formslib.php");

/**
 * Native Moodle form for availability creation and editing.
 */
final class availability_form extends \moodleform {
    /**
     * definition in Office Hours.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement("hidden", "id");
        $mform->setType("id", PARAM_INT);
        $mform->addElement("hidden", "ruleid");
        $mform->setType("ruleid", PARAM_INT);
        $mform->addElement("select", "kind", get_string("rulekind", "officehours"), [
            "weekly" => get_string("weekly", "officehours"),
            "single" => get_string("single", "officehours"),
        ]);
        $weekdays = [];
        foreach ([1 => "monday", 2 => "tuesday", 3 => "wednesday", 4 => "thursday",
                5 => "friday", 6 => "saturday", 7 => "sunday"] as $day => $name) {
            $weekdays[$day] = get_string($name, "calendar");
        }
        $mform->addElement("select", "weekday", get_string("weekday", "officehours"), $weekdays);
        $mform->addElement("date_selector", "startdate", get_string("startdate", "officehours"));
        $mform->addElement("date_selector", "enddate", get_string("enddate", "officehours"));
        foreach (["startminute", "endminute"] as $name) {
            $mform->addElement("text", $name, get_string($name, "officehours"), ["size" => 8]);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addElement("text", "timezone", get_string("timezone", "officehours"));
        $mform->setType("timezone", PARAM_TEXT);
        $mform->setDefault("timezone", \core_date::get_user_timezone());
        $mform->addElement("text", "capacity", get_string("capacity", "officehours"));
        $mform->setType("capacity", PARAM_INT);
        $mform->setDefault("capacity", 0);
        $this->add_action_buttons();
    }

    /**
     * validation in Office Hours.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data["startminute"] < 0 || $data["endminute"] > 1440 || $data["startminute"] >= $data["endminute"]) {
            $errors["endminute"] = get_string("invalidwindow", "officehours");
        }
        if ($data["enddate"] < $data["startdate"]) {
            $errors["enddate"] = get_string("invalidwindow", "officehours");
        }
        try {
            new \DateTimeZone($data["timezone"]);
        } catch (\Throwable $exception) {
            $errors["timezone"] = get_string("invalidtimezone", "officehours");
        }
        if ($data["capacity"] < 0) {
            $errors["capacity"] = get_string("nonnegative", "officehours");
        }
        return $errors;
    }
}
