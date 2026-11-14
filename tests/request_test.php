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
 * Test private academic support requests and permissions.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_officehours;

use advanced_testcase;
use mod_officehours\local\request_service;
use moodle_exception;
use required_capability_exception;

/**
 * Server-side private support access and lifecycle tests.
 */
final class request_test extends advanced_testcase {
    /**
     * Verify the relevant academic guidance behavior.
     *
     * @covers \mod_officehours\local\request_service::get
     * @covers \mod_officehours\local\request_service::reply
     */
    public function test_private_access_and_staff_only_notes(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('officehours', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        foreach ([$student, $other] as $person) {
            $this->getDataGenerator()->enrol_user($person->id, $course->id, 'student');
        }
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($student);
        $id = request_service::create((int)$activity->id, 'Calculus', 'Need guidance with derivation');
        $this->setUser($other);
        $this->expectException(required_capability_exception::class);
        request_service::get((int)$activity->id, $id);
    }

    /**
     * Verify the related academic guidance behavior.
     *
     * @covers \mod_officehours\local\request_service::messages
     */
    public function test_notes_are_hidden_from_students(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('officehours', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($student);
        $id = request_service::create((int)$activity->id, 'Topic', 'Need an explanation');
        $this->setUser($teacher);
        request_service::reply((int)$activity->id, $id, 'Staff-only note', true);
        request_service::reply((int)$activity->id, $id, 'Try the worked example');
        $record = request_service::get((int)$activity->id, $id);
        $this->assertCount(2, request_service::messages($record));
        $this->setUser($student);
        $this->assertCount(1, request_service::messages($record));
    }
}
