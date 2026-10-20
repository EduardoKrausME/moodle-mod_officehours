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
 * Access checks for office hours.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

use context_module;
use stdClass;

/**
 * Enforce permissions on every server-side mutation.
 */
final class access_manager {
    /**
     * load in Office Hours.
     */
    public static function load(int $cmid): array {
        $cm = get_coursemodule_from_id("officehours", $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        require_login($course, true, $cm);
        $context = context_module::instance($cm->id);
        require_capability("mod/officehours:view", $context);
        $activity = get_coursemodule_from_instance("officehours", $cm->instance);
        global $DB;
        $config = $DB->get_record("officehours", ["id" => $cm->instance], "*", MUST_EXIST);
        return [$cm, $course, $context, $config];
    }

    /**
     * capability in Office Hours.
     */
    public static function capability(stdClass $cm, string $name): void {
        require_capability("mod/officehours:" . $name, context_module::instance($cm->id));
    }

    /**
     * teacher in Office Hours.
     */
    public static function teacher(int $officehoursid, int $teacherid, string $capability): void {
        global $DB;
        $cm = get_coursemodule_from_instance("officehours", $officehoursid, 0, false, MUST_EXIST);
        self::capability($cm, $capability);
        if (has_capability("mod/officehours:manage", context_module::instance($cm->id))) {
            return;
        }
        global $USER;
        if ($teacherid !== (int)$USER->id) {
            throw new \required_capability_exception(context_module::instance($cm->id),
                "mod/officehours:manage", "nopermissions", "");
        }
    }

    /**
     * Separate groups and visible-group restrictions apply to bookings too.
     */
    public static function visible_student(stdClass $cm, int $studentid, int $viewerid): bool {
        if ($studentid === $viewerid) {
            return true;
        }
        $context = context_module::instance($cm->id);
        if (has_capability("moodle/site:accessallgroups", $context)) {
            return true;
        }
        if (groups_get_activity_groupmode($cm) !== SEPARATEGROUPS) {
            return true;
        }
        $viewer = groups_get_all_groups($cm->course, $viewerid, $cm->groupingid, "g.id");
        $student = groups_get_all_groups($cm->course, $studentid, $cm->groupingid, "g.id");
        return (bool)array_intersect(array_keys($viewer), array_keys($student));
    }
}
