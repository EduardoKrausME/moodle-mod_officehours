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
use mod_officehours\form\reply_form;
use mod_officehours\local\access_manager;
use mod_officehours\local\request_service;

require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$requestid = required_param('requestid', PARAM_INT);
[$cm, $course, $context, $activity] = access_manager::load($id);
$request = request_service::get((int)$activity->id, $requestid);
$staff = has_capability('mod/officehours:managerequests', $context);
$PAGE->set_url(new moodle_url('/mod/officehours/request.php', ['id' => $id, 'requestid' => $requestid]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($request->subject));
$PAGE->set_heading(format_string($course->fullname));
$back = new moodle_url('/mod/officehours/view.php', ['id' => $id]);
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'state') {
    require_sesskey();
    $next = required_param('next', PARAM_ALPHA);
    request_service::transition((int)$activity->id, $requestid, $next);
    redirect($PAGE->url, get_string('requestupdated', 'officehours'));
}
$form = new reply_form(null, ['staff' => $staff]);
$form->set_data(['id' => $id, 'requestid' => $requestid]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    request_service::reply((int)$activity->id, $requestid, $data->body, !empty($data->internal));
    redirect($PAGE->url, get_string('replysaved', 'officehours'));
}
$request = request_service::get((int)$activity->id, $requestid);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($request->subject));
echo html_writer::tag('p', format_text($request->description, FORMAT_PLAIN), ['class' => 'lead']);
echo html_writer::tag('p', get_string('requeststatus', 'officehours') . ': ' .
    get_string('requeststate' . $request->state, 'officehours'));
echo html_writer::start_tag('section', ['aria-label' => get_string('conversation', 'officehours')]);
echo $OUTPUT->heading(get_string('conversation', 'officehours'), 3);
foreach (request_service::messages($request) as $message) {
    $author = core_user::get_user((int)$message->userid);
    $by = $author ? fullname($author) : get_string('unknownuser', 'officehours');
    $heading = $by . ' - ' . userdate($message->timecreated);
    if ($message->internal) {
        $heading .= ' (' . get_string('internalreply', 'officehours') . ')';
    }
    echo html_writer::start_div('card mb-3 p-3');
    echo html_writer::tag('h4', s($heading), ['class' => 'h6']);
    echo format_text($message->body, FORMAT_PLAIN);
    echo html_writer::end_div();
}
echo html_writer::end_tag('section');
$choices = [];
if ($staff && $request->state === request_service::OPEN) {
    $choices[request_service::INPROGRESS] = get_string('markinprogress', 'officehours');
}
if ($staff && in_array($request->state, [request_service::OPEN, request_service::INPROGRESS], true)) {
    $choices[request_service::RESOLVED] = get_string('markresolved', 'officehours');
}
if ($request->state === request_service::RESOLVED) {
    $choices[request_service::CLOSED] = get_string('markclosed', 'officehours');
}
if (in_array($request->state, [request_service::RESOLVED, request_service::CLOSED], true)) {
    $choices[request_service::OPEN] = get_string('reopenrequest', 'officehours');
}
foreach ($choices as $state => $label) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false),
        'class' => 'd-inline-block me-2 mb-3']);
    foreach (['id' => $id, 'requestid' => $requestid, 'action' => 'state',
            'next' => $state, 'sesskey' => sesskey()] as $key => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    echo html_writer::tag('button', s($label), ['type' => 'submit', 'class' => 'btn btn-outline-primary']);
    echo html_writer::end_tag('form');
}
if ($request->state !== request_service::CLOSED) {
    echo $OUTPUT->heading(get_string('addreply', 'officehours'), 3);
    $form->display();
}
echo html_writer::link($back, get_string('back'), ['class' => 'btn btn-link']);
echo $OUTPUT->footer();
