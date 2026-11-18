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
 * Scheduled waiting list, reminder, and notification work.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\task;

use core\task\scheduled_task;
use mod_officehours\local\notification_service;
use mod_officehours\local\waiting_list_service;

/**
 * Reconcile time-based tasks without duplicated offers.
 */
final class maintenance extends scheduled_task {
    /**
     * get name in Office Hours.
     */
    public function get_name(): string {
        return get_string("maintenancetask", "officehours");
    }

    /**
     * execute in Office Hours.
     */
    public function execute(): void {
        waiting_list_service::expire_and_promote();
        notification_service::queue_reminders();
        notification_service::flush();
    }
}
