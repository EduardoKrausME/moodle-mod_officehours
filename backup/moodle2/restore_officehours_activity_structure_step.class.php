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
 * Restore and map activity references.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Mandatory double-quoted guard.
defined("MOODLE_INTERNAL") || die();

/**
 * Restore officehours activity structure step for the Office Hours activity.
 */
class restore_officehours_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define structure for the Office Hours activity.
     */
    protected function define_structure(): array {
        $paths = [new restore_path_element("officehours", "/activity/officehours"),
            new restore_path_element("officehours_availability", "/activity/officehours/availabilities/availability"),
            new restore_path_element("officehours_exception", "/activity/officehours/exceptions/exception"),
            new restore_path_element("officehours_slot", "/activity/officehours/slots/slot")];
        if ($this->get_setting_value("userinfo")) {
            $paths[] = new restore_path_element("officehours_booking", "/activity/officehours/slots/slot/bookings/booking");
            $paths[] = new restore_path_element("officehours_waitlist", "/activity/officehours/slots/slot/waits/wait");
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process officehours for the Office Hours activity.
     */
    protected function process_officehours($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record("officehours", $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping("officehours", $oldid, $newid);
    }

    /**
     * Process officehours availability for the Office Hours activity.
     */
    protected function process_officehours_availability($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);
        $data->officehoursid = $this->get_new_parentid("officehours");
        $data->teacherid = $this->get_mappingid("user", $data->teacherid, 0);
        $newid = $DB->insert_record("officehours_availability", $data);
        $this->set_mapping("officehours_availability", $oldid, $newid);
    }

    /**
     * Process officehours exception for the Office Hours activity.
     */
    protected function process_officehours_exception($data): void {
        global $DB;
        $data = (object)$data;
        unset($data->id);
        $data->officehoursid = $this->get_new_parentid("officehours");
        $data->teacherid = $this->get_mappingid("user", $data->teacherid, 0);
        $DB->insert_record("officehours_exception", $data);
    }

    /**
     * Process officehours slot for the Office Hours activity.
     */
    protected function process_officehours_slot($data): void {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        unset($data->id);
        $data->officehoursid = $this->get_new_parentid("officehours");
        $data->availabilityid = $this->get_mappingid("officehours_availability", $data->availabilityid, 0);
        $data->teacherid = $this->get_mappingid("user", $data->teacherid, 0);
        $newid = $DB->insert_record("officehours_slot", $data);
        $this->set_mapping("officehours_slot", $oldid, $newid);
    }

    /**
     * Process officehours booking for the Office Hours activity.
     */
    protected function process_officehours_booking($data): void {
        global $DB;
        $data = (object)$data;
        unset($data->id);
        $data->officehoursid = $this->get_new_parentid("officehours");
        $data->slotid = $this->get_new_parentid("officehours_slot");
        $data->userid = $this->get_mappingid("user", $data->userid, 0);
        if ($data->userid) {
            $data->remindersent = 1;
            $DB->insert_record("officehours_booking", $data);
        }
    }

    /**
     * Process officehours waitlist for the Office Hours activity.
     */
    protected function process_officehours_waitlist($data): void {
        global $DB;
        $data = (object)$data;
        unset($data->id);
        $data->officehoursid = $this->get_new_parentid("officehours");
        $data->slotid = $this->get_new_parentid("officehours_slot");
        $data->userid = $this->get_mappingid("user", $data->userid, 0);
        if ($data->userid) {
            $DB->insert_record("officehours_waitlist", $data);
        }
    }

    /**
     * After execute for the Office Hours activity.
     */
    protected function after_execute(): void {
        global $DB;
        $this->add_related_files("mod_officehours", "intro", null);
        $bookings = $DB->get_records("officehours_booking", ["officehoursid" => $this->get_new_parentid("officehours")]);
        foreach ($bookings as $booking) {
            if ($booking->state === "confirmed") {
                \mod_officehours\local\calendar_service::sync((int)$booking->id);
            }
        }
    }
}
