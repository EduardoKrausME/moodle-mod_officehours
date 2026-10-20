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
 * Teacher availability administration.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$ruleid = optional_param("ruleid", 0, PARAM_INT);
$action = optional_param("action", "edit", PARAM_ALPHA);
[$cm, $course, $context, $activity] = \mod_officehours\local\access_manager::load($id);
require_login($course, true, $cm);
\mod_officehours\local\access_manager::capability($cm, "createavailability");
$PAGE->set_url(new moodle_url("/mod/officehours/availability.php", ["id" => $id]));
$PAGE->set_title(get_string("availability", "officehours"));
$PAGE->set_context($context);
$back = new moodle_url("/mod/officehours/availability.php", ["id" => $id]);
if ($action === "delete") {
    require_sesskey();
    \mod_officehours\local\availability_service::delete((int)$activity->id, (int)$USER->id, $ruleid);
    redirect($back, get_string("availabilitydeleted", "officehours"));
}
if ($action === "block") {
    $form = new \mod_officehours\form\block_form(null);
    $form->set_data(["id" => $id]);
    if ($form->is_cancelled()) {
        redirect($back);
    }
    if ($data = $form->get_data()) {
        \mod_officehours\local\availability_service::block((int)$activity->id, (int)$USER->id,
            (int)$data->starttime, (int)$data->endtime, $data->reason ?? "");
        redirect($back, get_string("blocksaved", "officehours"));
    }
    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
    exit;
}
$form = new \mod_officehours\form\availability_form(null);
$rule = null;
if ($ruleid) {
    $rule = $DB->get_record("officehours_availability", ["id" => $ruleid,
        "officehoursid" => $activity->id, "teacherid" => $USER->id], "*", MUST_EXIST);
}
$form->set_data($rule ? array_merge((array)$rule, ["id" => $id, "ruleid" => $ruleid]) :
    ["id" => $id, "ruleid" => 0, "startminute" => 540, "endminute" => 720]);
if ($form->is_cancelled()) {
    redirect(new moodle_url("/mod/officehours/view.php", ["id" => $id]));
}
if ($data = $form->get_data()) {
    \mod_officehours\local\availability_service::save((int)$activity->id, (int)$USER->id,
        ["kind" => $data->kind, "weekday" => (int)$data->weekday,
            "startdate" => (int)$data->startdate, "enddate" => (int)$data->enddate,
            "startminute" => (int)$data->startminute, "endminute" => (int)$data->endminute,
            "timezone" => $data->timezone, "capacity" => (int)$data->capacity], (int)$data->ruleid);
    redirect($back, get_string("availabilitysaved", "officehours"));
}
$rules = $DB->get_records("officehours_availability", ["officehoursid" => $activity->id,
    "teacherid" => $USER->id, "enabled" => 1], "startdate DESC");
$view = ["entries" => [], "title" => get_string("availability", "officehours"),
    "blockurl" => (new moodle_url("/mod/officehours/availability.php", ["id" => $id, "action" => "block"]))->out(false),
    "blocklabel" => get_string("blocktime", "officehours"),
    "actionurl" => (new moodle_url("/mod/officehours/availability.php"))->out(false), "delete" => get_string("delete"),
    "edit" => get_string("edit"), "sesskey" => sesskey(), "cmid" => $id];
foreach ($rules as $item) {
    $view["entries"][] = ["id" => $item->id, "kind" => get_string($item->kind, "officehours"),
        "from" => userdate($item->startdate), "until" => userdate($item->enddate),
        "editurl" => (new moodle_url("/mod/officehours/availability.php", ["id" => $id, "ruleid" => $item->id]))->out(false)];
}
echo $OUTPUT->header();
echo $OUTPUT->render_from_template("mod_officehours/availability", $view);
$form->display();
echo $OUTPUT->footer();
