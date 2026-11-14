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
use mod_officehours\form\request_form;
use mod_officehours\local\access_manager;
use mod_officehours\local\request_service;

require_once('../../config.php');
$id = required_param('id', PARAM_INT);
[$cm, $course, $context, $activity] = access_manager::load($id);
access_manager::capability($cm, 'submitrequest');
$PAGE->set_url(new moodle_url('/mod/officehours/newrequest.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('newrequest', 'officehours'));
$form = new request_form();
$form->set_data(['id' => $id]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/officehours/view.php', ['id' => $id]));
}
if ($data = $form->get_data()) {
    $requestid = request_service::create((int)$activity->id, $data->subject, $data->description);
    redirect(new moodle_url('/mod/officehours/request.php', ['id' => $id, 'requestid' => $requestid]),
        get_string('requestcreated', 'officehours'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('newrequest', 'officehours'));
$form->display();
echo $OUTPUT->footer();
