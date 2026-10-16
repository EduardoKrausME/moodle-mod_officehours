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
 * Capabilities for office hours.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

$capabilities = [
    "mod/officehours:addinstance" => ["captype" => "write", "contextlevel" => CONTEXT_COURSE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:view" => ["captype" => "read", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW, "teacher" => CAP_ALLOW,
            "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:book" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW]],
    "mod/officehours:manageown" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["student" => CAP_ALLOW]],
    "mod/officehours:createavailability" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["teacher" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:manageavailability" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:approve" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["teacher" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:viewbookings" => ["captype" => "read", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["teacher" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:attendance" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["teacher" => CAP_ALLOW, "editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:reports" => ["captype" => "read", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
    "mod/officehours:manage" => ["captype" => "write", "contextlevel" => CONTEXT_MODULE,
        "archetypes" => ["editingteacher" => CAP_ALLOW, "manager" => CAP_ALLOW]],
];
