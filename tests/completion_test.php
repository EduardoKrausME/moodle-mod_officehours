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
namespace mod_officehours;

use advanced_testcase;
use mod_officehours\local\completion_service;
use mod_officehours\local\request_service;

/** Completion from private guidance cases, not bookings. */
final class completion_test extends advanced_testcase {
    /** @covers \mod_officehours\local\completion_service::complete */
    public function test_completion_from_guidance_requests(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('officehours', [
            'course' => $course->id, 'completionrequested' => 1, 'completionresolved' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($student);
        $this->assertFalse(completion_service::complete((int)$activity->id, (int)$student->id));
        $id = request_service::create((int)$activity->id, 'Exercise 3', 'Please explain the third step');
        $this->assertFalse(completion_service::complete((int)$activity->id, (int)$student->id));
        $this->setUser($teacher);
        request_service::transition((int)$activity->id, $id, request_service::RESOLVED);
        $this->assertTrue(completion_service::complete((int)$activity->id, (int)$student->id));
    }
}
