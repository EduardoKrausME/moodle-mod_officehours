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
 * Integration tests for booking state and capacity.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours;

use mod_officehours\local\booking_service;
use mod_officehours\local\slot_service;

/**
 * Database-backed reservation checks.
 */
final class booking_test extends \advanced_testcase {
    /**
     * Verify booking constraints and capacity.
     *
     * @covers \mod_officehours\local\booking_service::book
     * @covers \mod_officehours\local\slot_service::occupied
     */
    public function test_capacity_and_duplicate_booking(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module("officehours", ["course" => $course->id,
            "slotduration" => 30, "capacity" => 1, "maxbookings" => 3,
            "minnotice" => 0, "maxnotice" => 99999]);
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student1->id, $course->id, "student");
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, "student");
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, "editingteacher");
        $slotid = $DB->insert_record("officehours_slot", (object)[
            "officehoursid" => $activity->id, "availabilityid" => 0, "teacherid" => $teacher->id,
            "starttime" => time() + 86400, "endtime" => time() + 88200, "capacity" => 1,
            "disabled" => 0, "timecreated" => time(),
        ]);
        $this->setUser($student1);
        booking_service::book((int)$activity->id, $slotid, $student1->id);
        $this->assertSame(1, slot_service::occupied($slotid));
        try {
            booking_service::book((int)$activity->id, $slotid, $student1->id);
            $this->fail("Duplicate booking was accepted");
        } catch (\moodle_exception $exception) {
            $this->assertSame("duplicatebooking", $exception->errorcode);
        }
        $this->setUser($student2);
        $this->expectException(\moodle_exception::class);
        booking_service::book((int)$activity->id, $slotid, $student2->id);
    }

    /**
     * Document the Office Hours behaviour.
     * @covers \mod_officehours\local\booking_service::change_state
     */
    public function test_cancelled_booking_cannot_be_approved(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module("officehours", ["course" => $course->id,
            "approvalrequired" => 1, "minnotice" => 0, "maxnotice" => 99999, "cancelnotice" => 0]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, "student");
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, "editingteacher");
        $slotid = $DB->insert_record("officehours_slot", (object)[
            "officehoursid" => $activity->id, "availabilityid" => 0,
            "teacherid" => $teacher->id, "starttime" => time() + 86400,
            "endtime" => time() + 88200, "capacity" => 1, "disabled" => 0,
            "timecreated" => time(),
        ]);
        $this->setUser($student);
        $bookingid = booking_service::book((int)$activity->id, $slotid, $student->id);
        booking_service::change_state($bookingid, "cancelled", $student->id);
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        booking_service::change_state($bookingid, "confirmed", $teacher->id);
    }
}
