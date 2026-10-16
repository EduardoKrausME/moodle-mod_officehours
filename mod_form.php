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
 * Configuration form for office hours.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Mandatory double-quoted guard.
defined("MOODLE_INTERNAL") || die();
require_once($CFG->dirroot . "/course/moodleform_mod.php");

/**
 * Mod officehours mod form for the Office Hours activity.
 */
class mod_officehours_mod_form extends moodleform_mod {
    /**
     * Definition for the Office Hours activity.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement("header", "general", get_string("general"));
        $mform->addElement("text", "name", get_string("name"), ["size" => 64]);
        $mform->setType("name", PARAM_TEXT);
        $mform->addRule("name", null, "required", null, "client");
        $this->standard_intro_elements();
        $mform->addElement("select", "modality", get_string("modality", "officehours"), [
            "online" => get_string("online", "officehours"),
            "onsite" => get_string("onsite", "officehours"),
            "hybrid" => get_string("hybrid", "officehours"),
        ]);
        foreach (["slotduration" => 30, "capacity" => 1, "bufferminutes" => 0,
                "minnotice" => 60, "maxnotice" => 43200, "cancelnotice" => 60,
                "maxbookings" => 3, "offerexpires" => 120, "reminderminutes" => 60] as $name => $default) {
            $mform->addElement("text", $name, get_string($name, "officehours"), ["size" => 8]);
            $mform->setType($name, PARAM_INT);
            $mform->setDefault($name, $default);
        }
        foreach (["allowreschedule", "waitlistenabled", "approvalrequired", "showbusy", "allowsubject"] as $name) {
            $mform->addElement("advcheckbox", $name, get_string($name, "officehours"));
            $mform->setDefault($name, ($name === "allowreschedule" || $name === "waitlistenabled" || $name === "allowsubject") ? 1 : 0);
        }
        $mform->addElement("textarea", "additionalinfo", get_string("additionalinfo", "officehours"));
        $mform->setType("additionalinfo", PARAM_TEXT);
        $mform->addElement("text", "location", get_string("location", "officehours"));
        $mform->setType("location", PARAM_TEXT);
        $mform->addElement("text", "meetingurl", get_string("meetingurl", "officehours"));
        $mform->setType("meetingurl", PARAM_URL);
        $mform->addElement("date_time_selector", "timeopen", get_string("timeopen", "officehours"), ["optional" => true]);
        $mform->addElement("date_time_selector", "timeclose", get_string("timeclose", "officehours"), ["optional" => true]);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add completion rules for the Office Hours activity.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        foreach (["completionbooked", "completionattended"] as $field) {
            $name = $this->get_suffixed_name($field);
            $mform->addElement("advcheckbox", $name, get_string($field, "officehours"));
        }
        $count = $this->get_suffixed_name("completioncount");
        $mform->addElement("text", $count, get_string("completiontarget", "officehours"), ["size" => 5]);
        $mform->setType($count, PARAM_INT);
        $mform->setDefault($count, 0);
        return [$this->get_suffixed_name("completionbooked"),
            $this->get_suffixed_name("completionattended"), $count];
    }

    /**
     * Completion rule enabled for the Office Hours activity.
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data[$this->get_suffixed_name("completionbooked")]) ||
            !empty($data[$this->get_suffixed_name("completionattended")]) ||
            !empty($data[$this->get_suffixed_name("completioncount")]);
    }

    /**
     * Get data for the Office Hours activity.
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        foreach (["completionbooked", "completionattended", "completioncount"] as $field) {
            $suffix = $this->get_suffixed_name($field);
            if (property_exists($data, $suffix)) {
                $data->{$field} = (int)$data->{$suffix};
            }
        }
        return $data;
    }

    /**
     * Validation for the Office Hours activity.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (["slotduration", "capacity", "maxbookings", "offerexpires"] as $field) {
            if ((int)$data[$field] < 1) {
                $errors[$field] = get_string("positiveinteger", "officehours");
            }
        }
        foreach (["minnotice", "maxnotice", "cancelnotice", "bufferminutes", "reminderminutes"] as $field) {
            if ((int)$data[$field] < 0) {
                $errors[$field] = get_string("nonnegative", "officehours");
            }
        }
        $count = $this->get_suffixed_name("completioncount");
        if (isset($data[$count]) && (int)$data[$count] < 0) {
            $errors[$count] = get_string("nonnegative", "officehours");
        }
        if ((int)$data["minnotice"] > (int)$data["maxnotice"]) {
            $errors["maxnotice"] = get_string("invalidwindow", "officehours");
        }
        if (!empty($data["timeopen"]) && !empty($data["timeclose"]) && $data["timeclose"] <= $data["timeopen"]) {
            $errors["timeclose"] = get_string("invalidwindow", "officehours");
        }
        return $errors;
    }
}
