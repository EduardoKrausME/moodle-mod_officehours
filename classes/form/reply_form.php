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
namespace mod_officehours\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Private discussion reply, with optional teacher-only notes. */
final class reply_form extends moodleform {
    public function definition(): void {
        $form = $this->_form;
        foreach (['id', 'requestid'] as $field) {
            $form->addElement('hidden', $field);
            $form->setType($field, PARAM_INT);
        }
        $form->addElement('textarea', 'body', get_string('replybody', 'officehours'),
            ['rows' => 5, 'cols' => 70]);
        $form->setType('body', PARAM_TEXT);
        $form->addRule('body', null, 'required', null, 'client');
        if (!empty($this->_customdata['staff'])) {
            $form->addElement('advcheckbox', 'internal', get_string('internalreply', 'officehours'));
        }
        $this->add_action_buttons(true, get_string('sendreply', 'officehours'));
    }
}
