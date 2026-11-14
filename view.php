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
use mod_officehours\local\access_manager;

require_once('../../config.php');
$id = required_param('id', PARAM_INT);
[$cm, $course, $context, $activity] = access_manager::load($id);
$PAGE->set_url(new moodle_url('/mod/officehours/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$completion = new completion_info($course);
$completion->set_module_viewed($cm);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));
echo format_module_intro('officehours', $activity, $cm->id);
if ($activity->responsehours > 0) {
    echo html_writer::tag('p', get_string('responsehours', 'officehours') . ': ' .
        (int)$activity->responsehours, ['class' => 'text-muted']);
}
if (has_capability('mod/officehours:submitrequest', $context)) {
    echo html_writer::link(new moodle_url('/mod/officehours/newrequest.php', ['id' => $id]),
        get_string('newrequest', 'officehours'), ['class' => 'btn btn-primary mb-3']);
}
if (has_capability('mod/officehours:managerequests', $context)) {
    echo html_writer::link(new moodle_url('/mod/officehours/manage.php', ['id' => $id]),
        get_string('manageconsultations', 'officehours'), ['class' => 'btn btn-outline-primary ms-2 mb-3']);
}
$mine = $DB->get_records('officehours_request',
    ['officehoursid' => $activity->id, 'userid' => $USER->id], 'timemodified DESC', '*', 0, 100);
echo $OUTPUT->heading(get_string('myrequests', 'officehours'), 3);
if (!$mine) {
    echo $OUTPUT->notification(get_string('norequests', 'officehours'), 'info');
}
foreach ($mine as $request) {
    $link = new moodle_url('/mod/officehours/request.php', ['id' => $id, 'requestid' => $request->id]);
    echo html_writer::start_div('card mb-2 p-3');
    echo html_writer::link($link, format_string($request->subject));
    echo html_writer::tag('p', get_string('requeststate' . $request->state, 'officehours') .
        ' - ' . userdate($request->timemodified), ['class' => 'mb-0 text-muted']);
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
