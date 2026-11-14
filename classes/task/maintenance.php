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
namespace mod_officehours\task;

use core\task\scheduled_task;
use mod_officehours\local\notification_service;

/** Deliver private guidance notifications. Legacy booking reminders are disabled. */
final class maintenance extends scheduled_task {
    public function get_name(): string {
        return get_string('maintenancetask', 'officehours');
    }

    public function execute(): void {
        notification_service::flush();
    }
}
