/**
 * @file
 * Responsive resizing for Infogram Session Location embeds.
 *
 * Infogram sends an iframe.resize message containing the height
 * required by the embedded project. This listener applies that
 * height to the appropriate Session Location iframe.
 */

(function () {
  'use strict';

  window.addEventListener('message', function (event) {
    /*
     * Only accept messages from Infogram's embed domain.
     */
    if (event.origin !== 'https://e.infogram.com') {
      return;
    }

    let data = event.data;

    /*
     * Infogram sends its resize information as JSON text.
     */
    if (typeof data === 'string') {
      try {
        data = JSON.parse(data);
      }
      catch (error) {
        return;
      }
    }

    if (
      !data ||
      data.context !== 'iframe.resize'
    ) {
      return;
    }

    const height = Number(data.height);

    if (
      !Number.isFinite(height) ||
      height <= 0
    ) {
      return;
    }

    if (
      typeof data.src !== 'string' ||
      data.src === ''
    ) {
      return;
    }

    /*
     * Find the matching Infogram iframe.
     */
    document
      .querySelectorAll('iframe[data-ios-infogram]')
      .forEach(function (iframe) {
        let iframeUrl;
        let messageUrl;

        try {
          iframeUrl = new URL(
            iframe.src,
            window.location.href
          );

          messageUrl = new URL(
            data.src,
            window.location.href
          );
        }
        catch (error) {
          return;
        }

        if (
          iframeUrl.origin !== messageUrl.origin ||
          iframeUrl.pathname !== messageUrl.pathname
        ) {
          return;
        }

        const newHeight = Math.ceil(height);

        iframe.style.height = newHeight + 'px';

        iframe.setAttribute(
          'height',
          String(newHeight)
        );
      });
  });
})();