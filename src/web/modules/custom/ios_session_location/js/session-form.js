/**
 * @file
 * Editor conveniences for Session date/time fields.
 *
 * When an editor changes the Session Start Date/Time:
 * - the End Date is set to the corresponding date;
 * - the End Time is set to one hour after the Start Time;
 * - the calculated End Date/Time remains fully editable.
 *
 * Nothing is changed merely by opening an existing Session for editing.
 * The defaults are recalculated only after the editor changes a Start
 * Date or Start Time value.
 */

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.iosSessionDateTimeDefaults = {
    attach: function (context) {
      once(
        'ios-session-date-time-defaults',
        'input[name="field_start_date_time[0][value][date]"]',
        context
      ).forEach(function (startDateInput) {
        const form = startDateInput.closest('form');

        if (!form) {
          return;
        }

        const startTimeInput = form.querySelector(
          'input[name="field_start_date_time[0][value][time]"]'
        );

        const endDateInput = form.querySelector(
          'input[name="field_end_date_time[0][value][date]"]'
        );

        const endTimeInput = form.querySelector(
          'input[name="field_end_date_time[0][value][time]"]'
        );

        if (
          !startTimeInput ||
          !endDateInput ||
          !endTimeInput
        ) {
          return;
        }

        /**
         * Formats a Date object as YYYY-MM-DD for an HTML date input.
         */
        function formatDate(date) {
          const year = date.getFullYear();
          const month = String(date.getMonth() + 1).padStart(2, '0');
          const day = String(date.getDate()).padStart(2, '0');

          return year + '-' + month + '-' + day;
        }

        /**
         * Formats a Date object as HH:MM for an HTML time input.
         */
        function formatTime(date) {
          const hours = String(date.getHours()).padStart(2, '0');
          const minutes = String(date.getMinutes()).padStart(2, '0');

          return hours + ':' + minutes;
        }

        /**
         * Copies the Start date and calculates an End time one hour later.
         */
        function updateEndDateTime() {
          const startDateValue = startDateInput.value;
          const startTimeValue = startTimeInput.value;

          /*
           * A valid Start Date can be copied immediately, even if the
           * editor has not entered a Start Time yet.
           */
          if (startDateValue) {
            endDateInput.value = startDateValue;
          }

          /*
           * We need both values before calculating Start + 1 hour.
           */
          if (!startDateValue || !startTimeValue) {
            return;
          }

          const start = new Date(
            startDateValue + 'T' + startTimeValue
          );

          if (Number.isNaN(start.getTime())) {
            return;
          }

          start.setMinutes(start.getMinutes() + 60);

          /*
           * Setting both values also handles the unusual case where a
           * one-hour session crosses midnight.
           */
          endDateInput.value = formatDate(start);
          endTimeInput.value = formatTime(start);
        }

        startDateInput.addEventListener(
          'change',
          updateEndDateTime
        );

        startTimeInput.addEventListener(
          'change',
          updateEndDateTime
        );
      });
    }
  };
})(Drupal, once);