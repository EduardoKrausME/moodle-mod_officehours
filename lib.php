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
use mod_officehours\local\calendar_service;
use mod_officehours\local\completion_service;

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

function officehours_add_instance(stdClass $data, ?mod_officehours_mod_form $mform = null): int {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    return $DB->insert_record('officehours', $data);
}

function officehours_update_instance(stdClass $data, ?mod_officehours_mod_form $mform = null): bool {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('officehours', $data);
}

function officehours_delete_instance(int $id): bool {
    global $DB;
    if (!$DB->record_exists('officehours', ['id' => $id])) {
        return false;
    }
    $transaction = $DB->start_delegated_transaction();
    // Remove each old private calendar event before deleting historical data.
    foreach ($DB->get_records('officehours_booking', ['officehoursid' => $id]) as $booking) {
        calendar_service::remove((int)$booking->id);
    }
    foreach (['message', 'history', 'request', 'calendar', 'notification',
            'waitlist', 'booking', 'slot', 'exception', 'availability'] as $table) {
        $DB->delete_records('officehours_' . $table, ['officehoursid' => $id]);
    }
    $DB->delete_records('officehours', ['id' => $id]);
    $transaction->allow_commit();
    return true;
}

function officehours_get_coursemodule_info($coursemodule) {
    global $DB;
    $activity = $DB->get_record('officehours', ['id' => $coursemodule->instance]);
    if (!$activity) {
        return false;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('officehours', $activity, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        foreach (['completionrequested', 'completionresolved', 'completiontarget'] as $name) {
            $info->customdata['customcompletionrules'][$name] = (int)$activity->{$name};
        }
    }
    return $info;
}

function officehours_get_completion_active_rule_descriptions($cm): array {
    global $DB;
    $activity = $DB->get_record('officehours', ['id' => $cm->instance]);
    if (!$activity) {
        return [];
    }
    $descriptions = [];
    foreach (['completionrequested', 'completionresolved'] as $name) {
        if (!empty($activity->{$name})) {
            $descriptions[] = get_string($name, 'officehours');
        }
    }
    if ($activity->completiontarget) {
        $descriptions[] = get_string('completiontarget', 'officehours', $activity->completiontarget);
    }
    return $descriptions;
}

function officehours_get_completion_state($course, $cm, $userid, $type): bool {
    return completion_service::complete((int)$cm->instance, (int)$userid, (bool)$type);
}
