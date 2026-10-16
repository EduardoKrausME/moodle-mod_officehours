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
 * Form for availability blackout windows.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\form;

require_once($CFG->libdir . "/formslib.php");

/** Mark an unbooked period unavailable. */
final class block_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement("hidden", "id");
        $mform->setType("id", PARAM_INT);
        $mform->addElement("date_time_selector", "starttime", get_string("starttime", "officehours"));
        $mform->addElement("date_time_selector", "endtime", get_string("endtime", "officehours"));
        $mform->addElement("text", "reason", get_string("reason", "officehours"));
        $mform->setType("reason", PARAM_TEXT);
        $this->add_action_buttons(true, get_string("blocktime", "officehours"));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data["starttime"] >= $data["endtime"]) {
            $errors["endtime"] = get_string("invalidwindow", "officehours");
        }
        return $errors;
    }
}
