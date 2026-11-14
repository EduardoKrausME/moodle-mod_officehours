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
 * Tests for the Office Hours report labels.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours;

use advanced_testcase;
use mod_officehours\local\report_service;

/**
 * Verify CSV headings remain translated and in export order.
 */
final class report_test extends advanced_testcase {
    /**
     * CSV headings must use plugin language strings in the column order of the exported data.
     *
     * @covers \mod_officehours\local\report_service::csv_headers
     */
    public function test_csv_headers_use_language_strings(): void {
        $this->assertSame([
            get_string("csvbookingid", "mod_officehours"),
            get_string("csvstudentid", "mod_officehours"),
            get_string("csvfirstname", "mod_officehours"),
            get_string("csvlastname", "mod_officehours"),
            get_string("csvstartutc", "mod_officehours"),
            get_string("csvendutc", "mod_officehours"),
            get_string("csvstatus", "mod_officehours"),
            get_string("csvsubject", "mod_officehours"),
            get_string("csvoutcome", "mod_officehours"),
        ], report_service::csv_headers());
    }
}
