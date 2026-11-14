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

/** @package mod_officehours */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/** Configure private learning-support consultations, not bookable times. */
class mod_officehours_mod_form extends moodleform_mod {
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('header', 'general', get_string('general'));
        $form->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $form->setType('name', PARAM_TEXT);
        $form->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $form->addElement('text', 'responsehours', get_string('responsehours', 'officehours'), ['size' => 6]);
        $form->setType('responsehours', PARAM_INT);
        $form->setDefault('responsehours', 48);
        $form->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'officehours'),
            ['optional' => true]);
        $form->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'officehours'),
            ['optional' => true]);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function add_completion_rules(): array {
        $form = $this->_form;
        foreach (['completionrequested', 'completionresolved'] as $name) {
            $form->addElement('advcheckbox', $name, get_string($name, 'officehours'));
        }
        $form->addElement('text', 'completiontarget', get_string('completiontargetlabel', 'officehours'),
            ['size' => 5]);
        $form->setType('completiontarget', PARAM_INT);
        $form->setDefault('completiontarget', 0);
        return ['completionrequested', 'completionresolved', 'completiontarget'];
    }

    public function completion_rule_enabled($data): bool {
        return !empty($data['completionrequested']) || !empty($data['completionresolved']) ||
            !empty($data['completiontarget']);
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ((int)$data['responsehours'] < 1) {
            $errors['responsehours'] = get_string('positiveinteger', 'officehours');
        }
        if (isset($data['completiontarget']) && (int)$data['completiontarget'] < 0) {
            $errors['completiontarget'] = get_string('nonnegative', 'officehours');
        }
        if (!empty($data['timeopen']) && !empty($data['timeclose']) &&
                $data['timeopen'] >= $data['timeclose']) {
            $errors['timeclose'] = get_string('invalidwindow', 'officehours');
        }
        return $errors;
    }
}
