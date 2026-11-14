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
 * Deliver private academic guidance notifications.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_officehours\task;

use core\task\scheduled_task;
use mod_officehours\local\notification_service;

/**
 * Deliver private guidance notifications. Legacy booking reminders are disabled.
 */
final class maintenance extends scheduled_task {
    /**
     * Return the label for the scheduled task.
     */
    public function get_name(): string {
        return get_string('maintenancetask', 'officehours');
    }

    /**
     * Flush the pending academic guidance notifications.
     */
    public function execute(): void {
        notification_service::flush();
    }
}
