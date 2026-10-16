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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Office hours student agenda.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$day = optional_param("day", time(), PARAM_INT);
$teacher = optional_param("teacher", 0, PARAM_INT);
$rescheduleid = optional_param("reschedule", 0, PARAM_INT);
[$cm, $course, $context, $activity] = \mod_officehours\local\access_manager::load($id);
$PAGE->set_url(new moodle_url("/mod/officehours/view.php", ["id" => $id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd("mod_officehours/agenda", "init");
$completion = new completion_info($course);
$completion->set_module_viewed($cm);
$start = (int)(floor($day / DAYSECS) * DAYSECS);
$end = $start + 14 * DAYSECS;
\mod_officehours\local\slot_service::expand((int)$activity->id, max(time(), $start), $end);
$slots = $DB->get_records_select("officehours_slot",
    "officehoursid = :officeid AND starttime >= :fromtime AND starttime < :totime AND disabled = 0",
    ["officeid" => $activity->id, "fromtime" => $start, "totime" => $end], "starttime ASC, id ASC");
$rows = [];
$teachers = [];
foreach ($slots as $slot) {
    $staff = $DB->get_record("user", ["id" => $slot->teacherid]);
    if (!$staff) {
        continue;
    }
    $teachers[$slot->teacherid] = fullname($staff);
    if ($teacher && $teacher != $slot->teacherid) {
        continue;
    }
    $occupied = \mod_officehours\local\slot_service::occupied((int)$slot->id);
    $available = $occupied < $slot->capacity;
    if (!$available && !$activity->showbusy && !$activity->waitlistenabled) {
        continue;
    }
    $rows[] = [
        "cmid" => $id, "slotid" => $slot->id, "sesskey" => sesskey(),
        "reschedule" => (bool)$rescheduleid, "rescheduleid" => $rescheduleid,
        "rescheduleurl" => (new moodle_url("/mod/officehours/booking.php"))->out(false),
        "reschedulelabel" => get_string("reschedule", "officehours"),
        "date" => userdate($slot->starttime, get_string("strftimedatefullshort", "langconfig")),
        "time" => userdate($slot->starttime, get_string("strftimetime", "langconfig")),
        "teacher" => $teachers[$slot->teacherid], "duration" => (int)(($slot->endtime - $slot->starttime) / 60),
        "free" => $available,
        "bookurl" => (new moodle_url("/mod/officehours/booking.php", ["id" => $id, "slotid" => $slot->id]))->out(false),
        "waiturl" => (new moodle_url("/mod/officehours/booking.php", ["id" => $id, "slotid" => $slot->id, "action" => "wait"]))->out(false),
        "waitlist" => !$available && $activity->waitlistenabled,
        "available" => get_string("available", "officehours"), "busy" => get_string("busy", "officehours"),
        "reserve" => get_string("reserve", "officehours"), "joinwaiting" => get_string("joinwaiting", "officehours"),
    ];
}
$mybookings = $DB->get_records("officehours_booking", ["officehoursid" => $activity->id, "userid" => $USER->id],
    "created DESC", "*", 0, 100);
$mine = [];
foreach ($mybookings as $booking) {
    $slot = $DB->get_record("officehours_slot", ["id" => $booking->slotid]);
    if (!$slot) {
        continue;
    }
    $mine[] = [
        "cmid" => $id, "sesskey" => sesskey(), "bookingid" => $booking->id,
        "meetingurl" => $booking->state === "confirmed" ? $activity->meetingurl : "",
        "location" => $booking->state === "confirmed" ? $activity->location : "",
        "meetinglabel" => get_string("meetinglink", "officehours"),
        "cancellabel" => get_string("cancelbooking", "officehours"),
        "rescheduleurl" => (new moodle_url("/mod/officehours/view.php", ["id" => $id,
            "reschedule" => $booking->id]))->out(false),
        "reschedulelabel" => get_string("reschedule", "officehours"),
        "allowreschedule" => (bool)$activity->allowreschedule && in_array($booking->state, ["pending", "confirmed"]),
        "date" => userdate($slot->starttime), "state" => get_string("state" . str_replace("-", "", $booking->state), "officehours"),
        "subject" => s($booking->subject), "cancel" => in_array($booking->state, ["pending", "confirmed"]),
        "cancelurl" => (new moodle_url("/mod/officehours/booking.php", ["id" => $id, "bookingid" => $booking->id,
            "action" => "cancel"]))->out(false),
    ];
}
$offers = [];
$offered = $DB->get_records("officehours_waitlist", ["officehoursid" => $activity->id,
    "userid" => $USER->id, "status" => "offered"]);
foreach ($offered as $offer) {
    if ($offer->offereduntil <= time()) {
        continue;
    }
    $offeredslot = $DB->get_record("officehours_slot", ["id" => $offer->slotid]);
    if ($offeredslot) {
        $offers[] = ["date" => userdate($offeredslot->starttime),
            "offeredlabel" => get_string("offered", "officehours"),
            "acceptlabel" => get_string("acceptoffer", "officehours"),
            "accepturl" => (new moodle_url("/mod/officehours/booking.php", ["id" => $id,
                "slotid" => $offer->slotid, "offerid" => $offer->id]))->out(false)];
    }
}
$canmanage = has_capability("mod/officehours:createavailability", $context);
// Page output uses the standard Moodle module navigation.
$data = ["name" => format_string($activity->name),
    "intro" => format_module_intro("officehours", $activity, $cm->id),
    "rows" => $rows, "mine" => $mine, "offers" => $offers, "teachers" => [],
    "titleagenda" => get_string("agenda", "officehours"),
    "titlemine" => get_string("mybookings", "officehours"),
    "empty" => get_string("emptyslots", "officehours"),
    "manageurl" => (new moodle_url("/mod/officehours/manage.php", ["id" => $id]))->out(false),
    "availabilityurl" => (new moodle_url("/mod/officehours/availability.php", ["id" => $id]))->out(false),
    "canmanage" => $canmanage, "manage" => get_string("manage", "officehours"),
    "availability" => get_string("availability", "officehours"),
    "backurl" => (new moodle_url("/mod/officehours/view.php", ["id" => $id, "day" => $start - 7 * DAYSECS]))->out(false),
    "nexturl" => (new moodle_url("/mod/officehours/view.php", ["id" => $id, "day" => $start + 7 * DAYSECS]))->out(false),
    "previous" => get_string("previous"), "next" => get_string("next"),
];
foreach ($teachers as $teacherid => $teachername) {
    $data["teachers"][] = ["url" => (new moodle_url("/mod/officehours/view.php",
        ["id" => $id, "teacher" => $teacherid]))->out(false), "name" => $teachername];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_officehours/agenda", $data);
echo $OUTPUT->footer();
