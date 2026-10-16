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
 * Custom completion integration test.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours;

use mod_officehours\local\completion_service;

/** Automatic completion based on reservation state. */
final class completion_test extends \advanced_testcase {
    /** @covers \mod_officehours\local\completion_service::complete */
    public function test_completion_without_reservations_is_false(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module("officehours", ["course" => $course->id,
            "completionbooked" => 1, "completionattended" => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->assertFalse(completion_service::complete((int)$activity->id, (int)$student->id));
    }
}
