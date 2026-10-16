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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Create availability slots and guard concurrency.
 *
 * @package    mod_officehours
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_officehours\local;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use core\lock\lock_config;

/** Slot generation is idempotent and always uses UTC timestamps in storage. */
final class slot_service {
    public static function acquire(int $slotid): \core\lock\lock {
        $factory = lock_config::get_lock_factory("mod_officehours");
        $lock = $factory->get_lock("slot_" . $slotid, 15);
        if (!$lock) {
            throw new \moodle_exception("lockfailed", "officehours");
        }
        return $lock;
    }

    public static function expand(int $officehoursid, int $from, int $to): void {
        global $DB;
        $activity = $DB->get_record("officehours", ["id" => $officehoursid], "*", MUST_EXIST);
        $to = min($to, $from + 32 * DAYSECS);
        $rules = $DB->get_records("officehours_availability", ["officehoursid" => $officehoursid, "enabled" => 1]);
        $factory = lock_config::get_lock_factory("mod_officehours");
        $lock = $factory->get_lock("expand_" . $officehoursid, 20);
        if (!$lock) {
            throw new \moodle_exception("lockfailed", "officehours");
        }
        try {
            foreach ($rules as $rule) {
                $tz = new DateTimeZone($rule->timezone);
                $start = (new DateTimeImmutable("@" . $from))->setTimezone($tz)->setTime(0, 0);
                $end = (new DateTimeImmutable("@" . $to))->setTimezone($tz)->setTime(0, 0);
                for ($day = $start; $day <= $end; $day = $day->add(new DateInterval("P1D"))) {
                    $date = $day->format("Y-m-d");
                    $dateindex = (int)$day->format("N");
                    $firstday = (new DateTimeImmutable("@" . $rule->startdate))->setTimezone($tz)->format("Y-m-d");
                    $lastday = (new DateTimeImmutable("@" . $rule->enddate))->setTimezone($tz)->format("Y-m-d");
                    if ($date < $firstday || $date > $lastday) {
                        continue;
                    }
                    if ($rule->kind === "weekly" && $dateindex !== (int)$rule->weekday) {
                        continue;
                    }
                    if ($rule->kind === "single" && $date !== (new DateTimeImmutable("@" . $rule->startdate))->setTimezone($tz)->format("Y-m-d")) {
                        continue;
                    }
                    $step = (int)$activity->slotduration + (int)$activity->bufferminutes;
                    if ($step <= 0) {
                        continue;
                    }
                    for ($minute = (int)$rule->startminute; $minute + (int)$activity->slotduration <= (int)$rule->endminute; $minute += $step) {
                        $hour = sprintf("%02d:%02d:00", intdiv($minute, 60), $minute % 60);
                        $datehour = $date . " " . $hour;
                        $startutc = new DateTimeImmutable($datehour, $tz);
                        // Round-trip detects spring-forward times that do not exist locally.
                        if ($startutc->format("Y-m-d H:i:s") !== $datehour) {
                            continue;
                        }
                        $timestamp = $startutc->getTimestamp();
                        $ends = $timestamp + (int)$activity->slotduration * 60;
                        if ($timestamp < $from || $timestamp >= $to ||
                                ($activity->timeopen && $timestamp < $activity->timeopen) ||
                                ($activity->timeclose && $ends > $activity->timeclose)) {
                            continue;
                        }
                        if ($DB->record_exists_select("officehours_exception",
                                "officehoursid = :officeid AND teacherid = :teacher AND starttime < :ends AND endtime > :starts",
                                ["officeid" => $officehoursid, "teacher" => $rule->teacherid,
                                "ends" => $ends, "starts" => $timestamp])) {
                            continue;
                        }
                        if (!$DB->record_exists("officehours_slot", ["officehoursid" => $officehoursid,
                                "teacherid" => $rule->teacherid, "starttime" => $timestamp])) {
                            $DB->insert_record("officehours_slot", (object)[
                                "officehoursid" => $officehoursid, "availabilityid" => $rule->id,
                                "teacherid" => $rule->teacherid, "starttime" => $timestamp, "endtime" => $ends,
                                "capacity" => $rule->capacity ?: $activity->capacity,
                                "disabled" => 0, "timecreated" => time(),
                            ]);
                        }
                    }
                }
            }
        } finally {
            $lock->release();
        }
    }

    public static function occupied(int $slotid): int {
        global $DB;
        $params = ["slotid" => $slotid];
        return (int)$DB->count_records_select("officehours_booking",
                "slotid = :slotid AND state IN ('pending', 'confirmed')", $params) +
            (int)$DB->count_records_select("officehours_waitlist",
                "slotid = :offered AND status = :status AND offereduntil > :now",
                ["offered" => $slotid, "status" => "offered", "now" => time()]);
    }

    public static function open(int $slotid): bool {
        global $DB;
        $slot = $DB->get_record("officehours_slot", ["id" => $slotid], "*", MUST_EXIST);
        return !$slot->disabled && self::occupied($slotid) < (int)$slot->capacity;
    }
}
