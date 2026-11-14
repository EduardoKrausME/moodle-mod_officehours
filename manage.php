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
 * Display teachers' consultation management dashboard.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_officehours\local\access_manager;

require_once('../../config.php');
$id = required_param('id', PARAM_INT);
[$cm, $course, $context, $activity] = access_manager::load($id);
require_login($course, true, $cm);
access_manager::capability($cm, 'managerequests');
$PAGE->set_url(new moodle_url('/mod/officehours/manage.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageconsultations', 'officehours'));
$PAGE->set_heading(format_string($course->fullname));
$filter = optional_param('state', '', PARAM_ALPHA);
$conditions = ['officehoursid' => $activity->id];
if (in_array($filter, ['open', 'inprogress', 'resolved', 'closed'], true)) {
    $conditions['state'] = $filter;
}
$requests = $DB->get_records('officehours_request', $conditions, 'timemodified DESC', '*', 0, 500);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageconsultations', 'officehours'));
echo html_writer::start_div('mb-3');
foreach (['' => get_string('all'), 'open' => get_string('requeststateopen', 'officehours'),
        'inprogress' => get_string('requeststateinprogress', 'officehours'),
        'resolved' => get_string('requeststateresolved', 'officehours'),
        'closed' => get_string('requeststateclosed', 'officehours')] as $state => $label) {
    echo html_writer::link(new moodle_url('/mod/officehours/manage.php', ['id' => $id, 'state' => $state]),
        s($label), ['class' => 'btn btn-outline-secondary me-2 mb-2']);
}
echo html_writer::end_div();
$counts = ['open' => 0, 'inprogress' => 0, 'resolved' => 0, 'closed' => 0];
$allrequests = $DB->get_records('officehours_request',
    ['officehoursid' => $activity->id], '', 'id,userid,state');
foreach ($allrequests as $candidate) {
    if (access_manager::visible_student($cm, (int)$candidate->userid, (int)$USER->id)) {
        $counts[$candidate->state] = ($counts[$candidate->state] ?? 0) + 1;
    }
}
echo html_writer::start_div('row mb-4');
foreach ($counts as $state => $count) {
    echo html_writer::start_div('col-sm-6 col-md-3 mb-2');
    echo html_writer::start_div('card p-3');
    echo html_writer::tag('strong', (string)$count, ['class' => 'h3']);
    echo html_writer::tag('span', get_string('requeststate' . $state, 'officehours'));
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::end_div();
$visible = 0;
foreach ($requests as $request) {
    if (!access_manager::visible_student($cm, (int)$request->userid, (int)$USER->id)) {
        continue;
    }
    $visible++;
    $student = core_user::get_user((int)$request->userid);
    echo html_writer::start_div('card p-3 mb-2');
    echo html_writer::link(new moodle_url('/mod/officehours/request.php',
        ['id' => $id, 'requestid' => $request->id]), format_string($request->subject));
    echo html_writer::tag('p', s($student ? fullname($student) : get_string('unknownuser', 'officehours')) .
        ' - ' . get_string('requeststate' . $request->state, 'officehours') .
        ' - ' . userdate($request->timemodified), ['class' => 'mb-0 text-muted']);
    echo html_writer::end_div();
}
if (!$visible) {
    echo $OUTPUT->notification(get_string('norequests', 'officehours'), 'info');
}
echo html_writer::link(new moodle_url('/mod/officehours/view.php', ['id' => $id]),
    get_string('back'), ['class' => 'btn btn-link']);
echo $OUTPUT->footer();
