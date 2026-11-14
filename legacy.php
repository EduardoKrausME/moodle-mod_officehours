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
 * Display the read-only record of historical appointments.
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
$staff = has_capability('mod/officehours:managerequests', $context);
$page = max(0, optional_param('page', 0, PARAM_INT));
$pagesize = 50;
$conditions = ['officehoursid' => $activity->id];
if (!$staff) {
    $conditions['userid'] = $USER->id;
}
$PAGE->set_url(new moodle_url('/mod/officehours/legacy.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('legacyarchive', 'officehours'));
$PAGE->set_heading(format_string($course->fullname));
$rows = $DB->get_records('officehours_booking', $conditions, 'created DESC, id DESC',
    '*', $page * $pagesize, $pagesize);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('legacyarchive', 'officehours'));
echo html_writer::tag('p', get_string('legacyarchiveinfo', 'officehours'), ['class' => 'alert alert-info']);
$count = 0;
foreach ($rows as $booking) {
    if ($staff && !access_manager::visible_student($cm, (int)$booking->userid, (int)$USER->id)) {
        continue;
    }
    $slot = $DB->get_record('officehours_slot',
        ['id' => $booking->slotid, 'officehoursid' => $activity->id]);
    if (!$slot) {
        continue;
    }
    $count++;
    $student = core_user::get_user((int)$booking->userid);
    echo html_writer::start_div('card mb-2 p-3');
    echo html_writer::tag('strong', s($booking->subject ?: get_string('legacyarchive', 'officehours')));
    if ($staff) {
        echo html_writer::tag('p', s($student ? fullname($student) : get_string('unknownuser', 'officehours')));
    }
    echo html_writer::tag('p', userdate($slot->starttime) . ' - ' .
        get_string('state' . str_replace('-', '', $booking->state), 'officehours'),
        ['class' => 'text-muted']);
    if (!empty($booking->notes)) {
        echo format_text($booking->notes, FORMAT_PLAIN);
    }
    if ($staff && !empty($booking->internalnotes)) {
        echo html_writer::tag('p', get_string('internalreply', 'officehours'));
        echo format_text($booking->internalnotes, FORMAT_PLAIN);
    }
    echo html_writer::end_div();
}
if (!$count) {
    echo $OUTPUT->notification(get_string('norequests', 'officehours'), 'info');
}
if ($page > 0) {
    echo html_writer::link(new moodle_url('/mod/officehours/legacy.php', ['id' => $id,
        'page' => $page - 1]), get_string('previous'), ['class' => 'btn btn-outline-primary me-2']);
}
if (count($rows) === $pagesize) {
    echo html_writer::link(new moodle_url('/mod/officehours/legacy.php', ['id' => $id,
        'page' => $page + 1]), get_string('next'), ['class' => 'btn btn-outline-primary']);
}
echo html_writer::tag('p', html_writer::link(
    new moodle_url('/mod/officehours/view.php', ['id' => $id]), get_string('back')));
echo $OUTPUT->footer();
