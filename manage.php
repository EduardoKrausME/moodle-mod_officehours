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
 * Teacher dashboard and reservation decisions.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$period = optional_param("period", "week", PARAM_ALPHA);
$from = optional_param("from", time(), PARAM_INT);
$studentid = optional_param("studentid", 0, PARAM_INT);
$groupid = optional_param("groupid", 0, PARAM_INT);
$search = optional_param("search", "", PARAM_TEXT);
$action = optional_param("action", "", PARAM_ALPHA);
$bookingid = optional_param("bookingid", 0, PARAM_INT);
[$cm, $course, $context, $activity] = \mod_officehours\local\access_manager::load($id);
\mod_officehours\local\access_manager::capability($cm, "viewbookings");
$PAGE->set_url(new moodle_url("/mod/officehours/manage.php", ["id" => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string("teacherpanel", "officehours"));
if ($action === "update") {
    require_sesskey();
    $status = required_param("status", PARAM_ALPHAEXT);
    $internalnotes = optional_param("internalnotes", "", PARAM_TEXT);
    $outcome = optional_param("outcome", "", PARAM_TEXT);
    \mod_officehours\local\booking_service::change_state($bookingid, $status, (int)$USER->id,
        $internalnotes, $outcome);
    redirect(new moodle_url("/mod/officehours/manage.php", ["id" => $id]),
        get_string("changesaved", "officehours"));
}
$day = (int)(floor($from / DAYSECS) * DAYSECS);
$span = ["day" => DAYSECS, "week" => WEEKSECS, "month" => 31 * DAYSECS][$period] ?? WEEKSECS;
if (optional_param("export", 0, PARAM_BOOL)) {
    \mod_officehours\local\report_service::export($cm, $day, $day + $span, $studentid);
}
$bookings = \mod_officehours\local\report_service::bookings($cm, $day, $day + $span, $studentid);
$counts = array_fill_keys(["pending", "confirmed", "completed", "no-show", "cancelled", "rejected"], 0);
$records = [];
foreach ($bookings as $booking) {
    if ($groupid && !groups_is_member($groupid, (int)$booking->userid)) {
        continue;
    }
    if ($search !== "" && stripos(fullname($booking), $search) === false &&
            stripos($booking->subject ?? "", $search) === false) {
        continue;
    }
    $counts[$booking->state]++;
    $options = [];
    foreach (["confirmed", "rejected", "cancelled", "completed", "no-show"] as $status) {
        $allowed = ["pending" => ["confirmed", "rejected", "cancelled"],
            "confirmed" => ["cancelled", "completed", "no-show"]];
        if (in_array($status, $allowed[$booking->state] ?? [], true)) {
            $options[] = ["value" => $status, "name" => get_string("state" . str_replace("-", "", $status), "officehours")];
        }
    }
    $records[] = ["id" => $booking->id, "student" => fullname($booking),
        "date" => userdate($booking->starttime), "subject" => s($booking->subject),
        "state" => get_string("state" . str_replace("-", "", $booking->state), "officehours"),
        "options" => $options, "hasoptions" => !empty($options),
        "notes" => s($booking->internalnotes ?? ""), "outcome" => s($booking->outcome ?? "")];
}
$waitentries = $DB->get_records("officehours_waitlist", ["officehoursid" => $activity->id], "created ASC", "*", 0, 100);
$wait = [];
foreach ($waitentries as $entry) {
    if (\mod_officehours\local\access_manager::visible_student($cm, (int)$entry->userid, (int)$USER->id)) {
        $wait[] = ["student" => fullname($DB->get_record("user", ["id" => $entry->userid], "*", MUST_EXIST)),
            "status" => $entry->status, "created" => userdate($entry->created)];
    }
}
$data = ["title" => get_string("teacherpanel", "officehours"), "records" => $records,
    "wait" => $wait, "counts" => [], "sesskey" => sesskey(), "cmid" => $id,
    "searchlabel" => get_string("search", "officehours"), "filterlabel" => get_string("filter", "officehours"),
    "query" => $search, "periods" => [],
    "actionurl" => (new moodle_url("/mod/officehours/manage.php"))->out(false),
    "exporturl" => (new moodle_url("/mod/officehours/manage.php", ["id" => $id,
        "period" => $period, "from" => $day, "studentid" => $studentid, "export" => 1]))->out(false),
    "export" => get_string("exportcsv", "officehours"),
    "save" => get_string("savechanges"), "notes" => get_string("internalnotes", "officehours"),
    "outcome" => get_string("outcome", "officehours"),
    "waittitle" => get_string("waitinglist", "officehours"), "studentlabel" => get_string("student", "officehours"),
    "statuslabel" => get_string("status", "officehours"), "datelabel" => get_string("date"),
];
foreach (["day", "week", "month"] as $size) {
    $data["periods"][] = ["label" => get_string("period" . $size, "officehours"),
        "url" => (new moodle_url("/mod/officehours/manage.php", ["id" => $id,
            "period" => $size, "from" => $day, "groupid" => $groupid,
            "studentid" => $studentid]))->out(false)];
}
foreach ($counts as $status => $count) {
    $data["counts"][] = ["status" => get_string("state" . str_replace("-", "", $status), "officehours"), "count" => $count];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_officehours/manage", $data);
echo $OUTPUT->footer();
