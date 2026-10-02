(function (Drupal, once) {
  'use strict';

  /**
   * Automatically sets End Date/Time to one hour after Start Date/Time.
   *
   * The end value remains completely editable. This is only intended to give
   * editors a useful default instead of requiring them to enter the same date
   * and a similar time twice.
   */
  Drupal.behaviors.iosDateTimeHelper = {
    attach(context) {
      once('ios-datetime-helper', 'form.node-form', context).forEach((form) => {
        const startDate = form.querySelector(
          'input[name="field_start_date_time[0][value][date]"]'
        );

        const startTime = form.querySelector(
          'input[name="field_start_date_time[0][value][time]"]'
        );

        const endDate = form.querySelector(
          'input[name="field_end_date_time[0][value][date]"]'
        );

        const endTime = form.querySelector(
          'input[name="field_end_date_time[0][value][time]"]'
        );

        // Stop quietly if this form does not contain the expected fields.
        if (!startDate || !startTime || !endDate || !endTime) {
          return;
        }

        /**
         * Set the end date/time to exactly one hour after the start.
         */
        const updateEndDateTime = () => {
          if (!startDate.value || !startTime.value) {
            return;
          }

          const start = new Date(
            `${startDate.value}T${startTime.value}`
          );

          if (Number.isNaN(start.getTime())) {
            return;
          }

          const end = new Date(start.getTime() + (60 * 60 * 1000));

          const year = end.getFullYear();
          const month = String(end.getMonth() + 1).padStart(2, '0');
          const day = String(end.getDate()).padStart(2, '0');
          const hours = String(end.getHours()).padStart(2, '0');
          const minutes = String(end.getMinutes()).padStart(2, '0');

          endDate.value = `${year}-${month}-${day}`;
          endTime.value = `${hours}:${minutes}`;

          // Tell Drupal/browser listeners that the values changed.
          endDate.dispatchEvent(new Event('change', { bubbles: true }));
          endTime.dispatchEvent(new Event('change', { bubbles: true }));
        };

        startDate.addEventListener('change', updateEndDateTime);
        startTime.addEventListener('change', updateEndDateTime);
      });
    }
  };

})(Drupal, once);