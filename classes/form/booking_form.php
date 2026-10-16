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
 * Booking confirmation form.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\form;

require_once($CFG->libdir . "/formslib.php");

/** CSRF-protected booking confirmation. */
final class booking_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        foreach (["id", "slotid", "offerid"] as $name) {
            $mform->addElement("hidden", $name);
            $mform->setType($name, PARAM_INT);
        }
        if ($this->_customdata["allowsubject"]) {
            $mform->addElement("text", "subject", get_string("subject", "officehours"));
            $mform->setType("subject", PARAM_TEXT);
        }
        $mform->addElement("textarea", "notes", get_string("notes", "officehours"));
        $mform->setType("notes", PARAM_TEXT);
        $this->add_action_buttons(true, get_string("confirmbooking", "officehours"));
    }
}
