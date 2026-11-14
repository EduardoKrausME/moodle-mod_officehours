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

/** @package mod_officehours
 * Legacy appointment URLs are intentionally disabled. Previous booking records
 * remain archived for upgrade/backup/privacy purposes; no new slots are offered.
 */
use mod_officehours\local\access_manager;

require_once('../../config.php');
$id = required_param('id', PARAM_INT);
[$cm, $course, $context] = access_manager::load($id);
redirect(new moodle_url('/mod/officehours/view.php', ['id' => $id]),
    get_string('legacydisabled', 'officehours'));
