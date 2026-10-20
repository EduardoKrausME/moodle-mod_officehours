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
 * agenda.js
 *
 * @package   mod_officehours
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Native DOM controls for the appointment schedule.
define(["jquery"], function ($) {
    "use strict";

    const init = function () {
        const agenda = document.querySelector("[data-officehours-agenda]");
        if (!agenda) {
            return;
        }
        const cards = agenda.querySelectorAll(".officehours__slot");
        cards.forEach(function (card) {
            card.querySelectorAll("button[type=submit]").forEach(function (button) {
                button.addEventListener("click", function () {
                    // Native form validation and Moodle sesskey handling remain server-side.
                    button.dataset.actionStarted = "1";
                });
            });
        });
    };
    return {init: init};
});
