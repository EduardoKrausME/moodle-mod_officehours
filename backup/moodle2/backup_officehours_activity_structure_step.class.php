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
 * Backup activity and related entities.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Mandatory double-quoted guard.
defined("MOODLE_INTERNAL") || die();

/**
 * Backup officehours activity structure step for the Office Hours activity.
 */
class backup_officehours_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define structure for the Office Hours activity.
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value("userinfo");
        $activity = new backup_nested_element("officehours", ["id"], ["name", "intro", "introformat",
            "modality", "slotduration", "capacity", "bufferminutes", "minnotice", "maxnotice",
            "cancelnotice", "maxbookings", "allowreschedule", "waitlistenabled", "approvalrequired",
            "showbusy", "allowsubject", "additionalinfo", "location", "meetingurl", "timeopen",
            "timeclose", "offerexpires", "reminderminutes", "completionbooked", "completionattended",
            "completioncount", "timecreated", "timemodified"]);
        $availabilities = new backup_nested_element("availabilities");
        $availability = new backup_nested_element("availability", ["id"], ["teacherid", "kind", "weekday",
            "startdate", "enddate", "startminute", "endminute", "timezone", "capacity", "enabled",
            "timecreated", "timemodified"]);
        $exceptions = new backup_nested_element("exceptions");
        $exception = new backup_nested_element("exception", ["id"], ["teacherid", "starttime", "endtime", "reason"]);
        $slots = new backup_nested_element("slots");
        $slot = new backup_nested_element("slot", ["id"], ["availabilityid", "teacherid", "starttime",
            "endtime", "capacity", "disabled", "timecreated"]);
        $bookings = new backup_nested_element("bookings");
        $booking = new backup_nested_element("booking", ["id"], ["userid", "state", "subject", "notes",
            "internalnotes", "outcome", "created", "modified", "remindersent"]);
        $waits = new backup_nested_element("waits");
        $wait = new backup_nested_element("wait", ["id"], ["userid", "status", "created", "offereduntil", "modified"]);
        $activity->add_child($availabilities);
        $availabilities->add_child($availability);
        $activity->add_child($exceptions);
        $exceptions->add_child($exception);
        $activity->add_child($slots);
        $slots->add_child($slot);
        $slot->add_child($bookings);
        $bookings->add_child($booking);
        $slot->add_child($waits);
        $waits->add_child($wait);
        $activity->set_source_table("officehours", ["id" => backup::VAR_ACTIVITYID]);
        $availability->set_source_table("officehours_availability", ["officehoursid" => backup::VAR_PARENTID]);
        $exception->set_source_table("officehours_exception", ["officehoursid" => backup::VAR_PARENTID]);
        $slot->set_source_table("officehours_slot", ["officehoursid" => backup::VAR_PARENTID]);
        if ($userinfo) {
            $booking->set_source_table("officehours_booking", ["slotid" => backup::VAR_PARENTID]);
            $wait->set_source_table("officehours_waitlist", ["slotid" => backup::VAR_PARENTID]);
            $booking->annotate_ids("user", "userid");
            $wait->annotate_ids("user", "userid");
        }
        $availability->annotate_ids("user", "teacherid");
        $exception->annotate_ids("user", "teacherid");
        $slot->annotate_ids("user", "teacherid");
        $activity->annotate_files("mod_officehours", "intro", null);
        return $this->prepare_activity_structure($activity);
    }
}
