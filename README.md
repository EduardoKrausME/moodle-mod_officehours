# Moodle Office Hours

Office Hours is a course activity for private student/teacher appointments, availability rules, waiting lists, private calendar events, and appointment reports. The code targets Moodle 4.5 LTS and 5.x.

## Installation

Place this directory at `mod/officehours` in a Moodle checkout, then run Moodle's upgrade process. Configure an activity, create weekly or one-off teacher availability, and allow students to reserve generated slots.

## Constraints and operational considerations

Slots are stored as UTC Unix timestamps; availability rules carry a named IANA timezone. Slot generation runs lazily and is limited to 32 days per request. A Moodle Lock API backend must be configured for installations with concurrent workers. Notification delivery requires Moodle cron. Private booking data must not be exposed through public calendar events.

This package is a local working tree and has not yet passed the mandatory full Moodle test matrix or the external moodle-plugin-validate runner. Do not deploy it to production without these checks.
