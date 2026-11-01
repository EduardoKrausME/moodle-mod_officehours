# Office Hours

Office Hours adds a private appointment-booking activity to Moodle courses. Teachers publish times when they are available, students reserve suitable appointments, and both sides manage the meeting without maintaining a separate spreadsheet or exchanging messages to agree on a time.

The activity supports individual consultations, academic advising, assignment feedback, supervision meetings and other scheduled student-teacher conversations.

## How it works

A teacher adds an Office Hours activity to a course, defines the type of appointment and publishes availability. Students see bookable time slots inside the activity, choose an appointment and can manage their existing reservations according to the activity rules.

Teachers see their bookings in a dedicated management view and can follow up after meetings.

## Availability and booking rules

- **Recurring and one-off availability:** publish weekly appointment times or a specific date range, and block individual periods when unavailable.
- **Online, on-site or hybrid meetings:** define a location or meeting link appropriate to the appointment.
- **Appointment duration and capacity:** control the length of each session, the number of students per slot and optional gaps between appointments.
- **Booking windows:** decide how early students may reserve or cancel and how many appointments each student may hold.
- **Rescheduling:** allow students to move existing reservations when the activity permits it.
- **Approval:** require teacher approval before certain bookings are confirmed, when desired.
- **Waiting lists:** allow students to join the queue for full slots and receive an offer when a place becomes available.

These controls are useful when a teacher has limited consultation hours or when appointments need to be distributed fairly across a large class.

## Teacher management

The teacher's appointment view shows reservations and their status. Teachers can confirm or reject pending appointments, mark meetings as completed or record a no-show. Internal notes and outcomes help document follow-up work without exposing those details to the entire course.

Bookings can be filtered by day, week or month, student and group, and exported as CSV for administrative review.

## Student experience

Students can review available times and their existing reservations from the course activity. The subject of a meeting can be collected when enabled, helping teachers prepare for the conversation.

Depending on the settings, students may cancel, reschedule or join a waiting list. Booking details are private; the activity does not publish the names or subjects of appointments to other students.

## Calendar, notifications and completion

Office Hours integrates with Moodle's calendar using private appointment information and can send booking-related notifications and reminders. Teachers can also configure activity-completion rules based on reservations and attendance.

Administrators should ensure Moodle cron is running so scheduled notifications and reminders can be delivered.

## Operational considerations

Availability is configured in a named time zone, and the activity handles booking times consistently for users in other locations. Teachers should confirm the time zone and availability periods before opening reservations.

Busy environments should have reliable Moodle locking configured so simultaneous requests cannot claim the same remaining place.

**Compatibility:** Moodle 4.5 LTS and Moodle 5.x.

**License:** GNU GPL v3 or later.
