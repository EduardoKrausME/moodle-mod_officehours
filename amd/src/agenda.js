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
