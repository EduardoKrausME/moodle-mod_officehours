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
 * Define the learner's private guidance request form.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_officehours\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Private learning-support request.
 */
final class request_form extends moodleform {
    /**
     * Define the fields and buttons for this Moodle form.
     */
    public function definition(): void {
        $form = $this->_form;
        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);
        $form->addElement('text', 'subject', get_string('requestsubject', 'officehours'), ['size' => 60]);
        $form->setType('subject', PARAM_TEXT);
        $form->addRule('subject', null, 'required', null, 'client');
        $form->addElement('textarea', 'description', get_string('requestdescription', 'officehours'),
            ['rows' => 7, 'cols' => 70]);
        $form->setType('description', PARAM_TEXT);
        $form->addRule('description', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('sendrequest', 'officehours'));
    }
}
