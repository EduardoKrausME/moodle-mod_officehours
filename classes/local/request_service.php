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
 * Private pedagogical consultation requests (not appointment reservations).
 *
 * @package mod_officehours
 */
namespace mod_officehours\local;

use context_module;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Request workflow; all entry points verify the current actor and activity.
 */
final class request_service {
    public const OPEN = 'open';
    public const INPROGRESS = 'inprogress';
    public const RESOLVED = 'resolved';
    public const CLOSED = 'closed';

    /**
     * Verify access to a private request in its own course activity.
     */
    public static function get(int $officehoursid, int $requestid): stdClass {
        global $DB, $USER;
        $cm = get_coursemodule_from_instance('officehours', $officehoursid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        require_capability('mod/officehours:view', $context);
        $request = $DB->get_record('officehours_request',
            ['id' => $requestid, 'officehoursid' => $officehoursid], '*', MUST_EXIST);
        if ((int)$request->userid === (int)$USER->id) {
            return $request;
        }
        require_capability('mod/officehours:managerequests', $context);
        if (!access_manager::visible_student($cm, (int)$request->userid, (int)$USER->id)) {
            throw new moodle_exception('nopermissions', 'error', '', null);
        }
        return $request;
    }

    /**
     * Submit a learning difficulty or private academic guidance request.
     */
    public static function create(int $officehoursid, string $subject, string $description): int {
        global $DB, $USER;
        $cm = get_coursemodule_from_instance('officehours', $officehoursid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        require_capability('mod/officehours:submitrequest', $context);
        $activity = $DB->get_record('officehours', ['id' => $officehoursid], '*', MUST_EXIST);
        $now = time();
        if (($activity->timeopen && $now < $activity->timeopen) ||
                ($activity->timeclose && $now > $activity->timeclose)) {
            throw new moodle_exception('requestclosed', 'officehours');
        }
        $subject = trim($subject);
        $description = trim($description);
        if ($subject === '' || $description === '') {
            throw new moodle_exception('required');
        }
        $record = (object)[
            'officehoursid' => $officehoursid, 'userid' => (int)$USER->id, 'teacherid' => 0,
            'subject' => $subject, 'description' => $description, 'state' => self::OPEN,
            'timecreated' => $now, 'timemodified' => $now, 'timeclosed' => 0,
        ];
        $id = $DB->insert_record('officehours_request', $record);
        $DB->insert_record('officehours_history', (object)[
            'officehoursid' => $officehoursid, 'requestid' => $id, 'userid' => (int)$USER->id,
            'oldstate' => '', 'newstate' => self::OPEN, 'timecreated' => $now,
        ]);
        completion_service::update($officehoursid, (int)$USER->id);
        notification_service::queue_request($id, 'created');
        return $id;
    }

    /**
     * Reply in a private discussion. Internal notes are staff-only.
     */
    public static function reply(int $officehoursid, int $requestid, string $body, bool $internal = false): void {
        global $DB, $USER;
        $request = self::get($officehoursid, $requestid);
        $cm = get_coursemodule_from_instance('officehours', $officehoursid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $staff = has_capability('mod/officehours:managerequests', $context);
        if ($internal && !$staff) {
            throw new moodle_exception('nopermissions', 'error');
        }
        if ($request->state === self::CLOSED) {
            throw new moodle_exception('requestisclosed', 'officehours');
        }
        $body = trim($body);
        if ($body === '') {
            throw new moodle_exception('required');
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('officehours_message', (object)[
            'officehoursid' => $officehoursid, 'requestid' => $requestid,
            'userid' => (int)$USER->id, 'body' => $body, 'internal' => (int)$internal,
            'timecreated' => $now,
        ]);
        $update = (object)['id' => $requestid, 'timemodified' => $now];
        if ($staff && $request->state === self::OPEN) {
            $update->state = self::INPROGRESS;
        }
        if ($staff && !$request->teacherid) {
            $update->teacherid = (int)$USER->id;
        }
        $DB->update_record('officehours_request', $update);
        $transaction->allow_commit();
        if (!$internal) {
            notification_service::queue_request($requestid, 'reply');
        }
    }

    /**
     * Explicit, audited state transitions with distinct student/staff rights.
     */
    public static function transition(int $officehoursid, int $requestid, string $next): void {
        global $DB, $USER;
        $request = self::get($officehoursid, $requestid);
        $cm = get_coursemodule_from_instance('officehours', $officehoursid, 0, false, MUST_EXIST);
        $staff = has_capability('mod/officehours:managerequests', context_module::instance($cm->id));
        $allowed = [
            self::OPEN => [self::INPROGRESS, self::RESOLVED],
            self::INPROGRESS => [self::RESOLVED],
            self::RESOLVED => [self::CLOSED, self::OPEN],
            self::CLOSED => [self::OPEN],
        ];
        if (!in_array($next, $allowed[$request->state] ?? [], true)) {
            throw new moodle_exception('invalidrequesttransition', 'officehours');
        }
        if ($next === self::INPROGRESS || $next === self::RESOLVED) {
            if (!$staff) {
                throw new moodle_exception('nopermissions', 'error');
            }
        } else if ($next === self::CLOSED) {
            if ((int)$request->userid !== (int)$USER->id && !$staff) {
                throw new moodle_exception('nopermissions', 'error');
            }
        } else if ((int)$request->userid !== (int)$USER->id && !$staff) {
            throw new moodle_exception('nopermissions', 'error');
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        // Lock the request row through an atomic compare-and-swap to avoid stale transitions.
        $count = $DB->count_records('officehours_request',
            ['id' => $requestid, 'officehoursid' => $officehoursid, 'state' => $request->state]);
        if (!$count) {
            throw new moodle_exception('staleoperation', 'officehours');
        }
        $DB->set_field('officehours_request', 'state', $next, ['id' => $requestid, 'state' => $request->state]);
        $DB->set_field('officehours_request', 'timemodified', $now, ['id' => $requestid]);
        $DB->set_field('officehours_request', 'timeclosed', $next === self::CLOSED ? $now : 0,
            ['id' => $requestid]);
        $DB->insert_record('officehours_history', (object)[
            'officehoursid' => $officehoursid, 'requestid' => $requestid,
            'userid' => (int)$USER->id, 'oldstate' => $request->state,
            'newstate' => $next, 'timecreated' => $now,
        ]);
        $transaction->allow_commit();
        completion_service::update($officehoursid, (int)$request->userid);
        notification_service::queue_request($requestid, 'state');
    }

    /**
     * List messages, hiding notes from request owners.
     */
    public static function messages(stdClass $request): array {
        global $DB, $USER;
        $cm = get_coursemodule_from_instance('officehours', $request->officehoursid, 0, false, MUST_EXIST);
        $staff = has_capability('mod/officehours:managerequests', context_module::instance($cm->id));
        self::get((int)$request->officehoursid, (int)$request->id);
        $conditions = ['requestid' => $request->id, 'officehoursid' => $request->officehoursid];
        if (!$staff) {
            $conditions['internal'] = 0;
        }
        return $DB->get_records('officehours_message', $conditions, 'timecreated ASC, id ASC');
    }
}
