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
 * Reservation and waiting-list actions.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$slotid = optional_param("slotid", 0, PARAM_INT);
$bookingid = optional_param("bookingid", 0, PARAM_INT);
$offerid = optional_param("offerid", 0, PARAM_INT);
$action = optional_param("action", "book", PARAM_ALPHA);
[$cm, $course, $context, $activity] = \mod_officehours\local\access_manager::load($id);
require_login($course, true, $cm);
$PAGE->set_url(new moodle_url("/mod/officehours/booking.php", ["id" => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string("reserve", "officehours"));
$back = new moodle_url("/mod/officehours/view.php", ["id" => $id]);
if ($action === "wait") {
    require_sesskey();
    \mod_officehours\local\waiting_list_service::join($slotid, (int)$USER->id);
    redirect($back, get_string("waitlisted", "officehours"));
}
if ($action === "cancel") {
    require_sesskey();
    \mod_officehours\local\booking_service::change_state($bookingid, "cancelled", (int)$USER->id);
    redirect($back, get_string("cancelled", "officehours"));
}
if ($action === "reschedule") {
    require_sesskey();
    \mod_officehours\local\booking_service::reschedule($bookingid, $slotid, (int)$USER->id);
    redirect($back, get_string("rescheduled", "officehours"));
}
$form = new \mod_officehours\form\booking_form(null, ["allowsubject" => (bool)$activity->allowsubject]);
$form->set_data(["id" => $id, "slotid" => $slotid, "offerid" => $offerid]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($data = $form->get_data()) {
    \mod_officehours\local\booking_service::book((int)$activity->id,
        (int)$data->slotid, (int)$USER->id, $data->subject ?? "", $data->notes ?? "", (int)$data->offerid);
    redirect($back, get_string("bookingsaved", "officehours"));
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
