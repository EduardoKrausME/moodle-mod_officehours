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
 * Upgrade Office Hours activity structures.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Retain previous appointments while adding academic guidance records.
 */
/**
 * Perform the Moodle module database upgrade.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 */
function xmldb_officehours_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2026111400) {
        $activity = new xmldb_table('officehours');
        $newfields = [
            ['responsehours', XMLDB_TYPE_INTEGER, '10', '48'],
            ['completionrequested', XMLDB_TYPE_INTEGER, '1', '0'],
            ['completionresolved', XMLDB_TYPE_INTEGER, '1', '0'],
            ['completiontarget', XMLDB_TYPE_INTEGER, '10', '0'],
        ];
        foreach ($newfields as [$name, $type, $length, $default]) {
            $field = new xmldb_field($name, $type, $length, null, XMLDB_NOTNULL, null, $default);
            if (!$dbman->field_exists($activity, $field)) {
                $dbman->add_field($activity, $field);
            }
        }
        $tables = [
            'officehours_request' => [
                ['id', XMLDB_TYPE_INTEGER, '10', null, true],
                ['officehoursid', XMLDB_TYPE_INTEGER, '10', null],
                ['userid', XMLDB_TYPE_INTEGER, '10', null],
                ['teacherid', XMLDB_TYPE_INTEGER, '10', '0'],
                ['subject', XMLDB_TYPE_CHAR, '255', null],
                ['description', XMLDB_TYPE_TEXT, null, null],
                ['state', XMLDB_TYPE_CHAR, '20', 'open'],
                ['timecreated', XMLDB_TYPE_INTEGER, '10', '0'],
                ['timemodified', XMLDB_TYPE_INTEGER, '10', '0'],
                ['timeclosed', XMLDB_TYPE_INTEGER, '10', '0'],
            ],
            'officehours_message' => [
                ['id', XMLDB_TYPE_INTEGER, '10', null, true],
                ['officehoursid', XMLDB_TYPE_INTEGER, '10', null],
                ['requestid', XMLDB_TYPE_INTEGER, '10', null],
                ['userid', XMLDB_TYPE_INTEGER, '10', null],
                ['body', XMLDB_TYPE_TEXT, null, null],
                ['internal', XMLDB_TYPE_INTEGER, '1', '0'],
                ['timecreated', XMLDB_TYPE_INTEGER, '10', '0'],
            ],
            'officehours_history' => [
                ['id', XMLDB_TYPE_INTEGER, '10', null, true],
                ['officehoursid', XMLDB_TYPE_INTEGER, '10', null],
                ['requestid', XMLDB_TYPE_INTEGER, '10', null],
                ['userid', XMLDB_TYPE_INTEGER, '10', null],
                ['oldstate', XMLDB_TYPE_CHAR, '20', null],
                ['newstate', XMLDB_TYPE_CHAR, '20', null],
                ['timecreated', XMLDB_TYPE_INTEGER, '10', '0'],
            ],
        ];
        foreach ($tables as $name => $fields) {
            $table = new xmldb_table($name);
            if ($dbman->table_exists($table)) {
                continue;
            }
            foreach ($fields as $spec) {
                [$fieldname, $type, $length, $default] = $spec;
                $sequence = $spec[4] ?? false;
                $table->add_field($fieldname, $type, $length, null, XMLDB_NOTNULL,
                    !empty($sequence) ? XMLDB_SEQUENCE : null, $default);
            }
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('officehours_fk', XMLDB_KEY_FOREIGN, ['officehoursid'], 'officehours', ['id']);
            if ($name === 'officehours_request') {
                $table->add_index('courseuser_ix', XMLDB_INDEX_NOTUNIQUE, ['officehoursid', 'userid']);
                $table->add_index('state_ix', XMLDB_INDEX_NOTUNIQUE, ['officehoursid', 'state']);
            } else {
                $table->add_index('request_ix', XMLDB_INDEX_NOTUNIQUE, ['requestid', 'timecreated']);
            }
            $dbman->create_table($table);
        }
        // Old booking, slot and waiting-list tables are intentionally untouched.
        // Preserve the old completion configuration in its legacy fields, but do not
        // make past appointment activity count as future pedagogical support.
        // Teachers may enable the new completion rules explicitly.
        upgrade_mod_savepoint(true, 2026111400, 'officehours');
    }
    return true;
}
