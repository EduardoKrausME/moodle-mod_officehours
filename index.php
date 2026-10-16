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
 * Course activity listing.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->libdir . "/modinfolib.php");

$id = required_param("id", PARAM_INT);
$course = get_course($id);
require_login($course);
$PAGE->set_url(new moodle_url("/mod/officehours/index.php", ["id" => $id]));
$PAGE->set_context(context_course::instance($id));
$PAGE->set_title(get_string("modulenameplural", "officehours"));
echo $OUTPUT->header();
$items = [];
foreach (get_fast_modinfo($course)->get_instances_of("officehours") as $cm) {
    if ($cm->uservisible) {
        $items[] = ["url" => (new moodle_url("/mod/officehours/view.php", ["id" => $cm->id]))->out(false),
            "name" => $cm->name];
    }
}
echo $OUTPUT->render_from_template("mod_officehours/index", ["items" => $items]);
echo $OUTPUT->footer();
