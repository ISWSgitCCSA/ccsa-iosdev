(function (Drupal) {

  Drupal.behaviors.countdown = {
    attach: function (context) {

      // Set the date we're counting down to.
      var countDownDate = new Date("Nov 3, 2027 10:00:00").getTime();

      // Find the countdown elements.
      var daysElements = context.querySelectorAll('.countdown__days');
      var hoursElements = context.querySelectorAll('.countdown__hours');
      var minsElements = context.querySelectorAll('.countdown__mins');
      var secsElements = context.querySelectorAll('.countdown__secs');

      // Don't start the countdown if the elements aren't present.
      if (!daysElements.length) {
        return;
      }

      // Update the countdown every 1 second.
      var x = setInterval(function () {

        // Get today's date and time.
        var now = new Date().getTime();

        // Find the distance between now and the countdown date.
        var distance = countDownDate - now;

        // Time calculations for days, hours, minutes and seconds.
        var days = Math.floor(distance / (1000 * 60 * 60 * 24));
        var hours = Math.floor(
          (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
        );
        var minutes = Math.floor(
          (distance % (1000 * 60 * 60)) / (1000 * 60)
        );
        var seconds = Math.floor(
          (distance % (1000 * 60)) / 1000
        );

        // Update all countdown instances.
        daysElements.forEach(function (element) {
          element.innerHTML = days;
        });

        hoursElements.forEach(function (element) {
          element.innerHTML = hours;
        });

        minsElements.forEach(function (element) {
          element.innerHTML = minutes;
        });

        secsElements.forEach(function (element) {
          element.innerHTML = seconds;
        });

        // If the countdown is finished.
        if (distance < 0) {
          clearInterval(x);

          daysElements.forEach(function (element) {
            element.innerHTML = '00';
          });

          hoursElements.forEach(function (element) {
            element.innerHTML = '00';
          });

          minsElements.forEach(function (element) {
            element.innerHTML = '00';
          });

          secsElements.forEach(function (element) {
            element.innerHTML = '00';
          });
        }

      }, 1000);
    }
  };

})(Drupal);
