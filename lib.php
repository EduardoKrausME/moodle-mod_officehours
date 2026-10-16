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
 * Moodle activity callbacks.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Mandatory double-quoted guard.
defined("MOODLE_INTERNAL") || die();

/**
 * Office Hours callback.
 * @return bool
 */
function officehours_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        default:
            return null;
    }
}

/**
 * Add an instance.
 */
function officehours_add_instance(stdClass $data, ?mod_officehours_mod_form $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    return $DB->insert_record("officehours", $data);
}

/**
 * Update an instance.
 */
function officehours_update_instance(stdClass $data, ?mod_officehours_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record("officehours", $data);
}

/**
 * Delete an instance and its private data.
 */
function officehours_delete_instance(int $id): bool {
    global $DB;
    if (!$DB->record_exists("officehours", ["id" => $id])) {
        return false;
    }
    $transaction = $DB->start_delegated_transaction();
    $bookings = $DB->get_records("officehours_booking", ["officehoursid" => $id]);
    foreach ($bookings as $booking) {
        \mod_officehours\local\calendar_service::remove((int)$booking->id);
    }
    foreach (["notification", "waitlist", "booking", "slot", "exception", "availability"] as $table) {
        $DB->delete_records("officehours_" . $table, ["officehoursid" => $id]);
    }
    $DB->delete_records("officehours", ["id" => $id]);
    $transaction->allow_commit();
    return true;
}

/**
 * Expose completion settings in cached course module metadata.
 */
function officehours_get_coursemodule_info($coursemodule) {
    global $DB;
    $activity = $DB->get_record("officehours", ["id" => $coursemodule->instance]);
    if (!$activity) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro("officehours", $activity, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        foreach (["completionbooked", "completionattended", "completioncount"] as $name) {
            $info->customdata["customcompletionrules"][$name] = (int)$activity->{$name};
        }
    }
    return $info;
}

/**
 * Describe custom completion rules.
 */
function officehours_get_completion_active_rule_descriptions($cm): array {
    global $DB;
    $instance = $DB->get_record("officehours", ["id" => $cm->instance]);
    if (!$instance) {
        return [];
    }
    $rules = [];
    if (!empty($instance->completionbooked)) {
        $rules[] = get_string("completionbooked", "officehours");
    }
    if (!empty($instance->completionattended)) {
        $rules[] = get_string("completionattended", "officehours");
    }
    if (!empty($instance->completioncount)) {
        $rules[] = get_string("completioncount", "officehours", $instance->completioncount);
    }
    return $rules;
}

/**
 * Evaluate custom completion rules for a student.
 */
function officehours_get_completion_state($course, $cm, $userid, $type): bool {
    return \mod_officehours\local\completion_service::complete((int)$cm->instance, (int)$userid, (bool)$type);
}
