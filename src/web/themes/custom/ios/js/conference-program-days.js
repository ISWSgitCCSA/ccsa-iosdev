(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.iosConferenceProgramDays = {
    attach(context) {
      once(
        'ios-conference-program-days',
        '[data-ios-program-days]',
        context
      ).forEach((program) => {
        const tabNavigation = program.querySelector(
          '[data-program-day-nav]'
        );

        const tabs = Array.from(
          program.querySelectorAll('[role="tab"]')
        );

        const panels = Array.from(
          program.querySelectorAll('[role="tabpanel"]')
        );

        if (!tabNavigation || !tabs.length || !panels.length) {
          return;
        }

        /**
         * Determine a tab's weekday from its YYYYMMDD program date.
         *
         * Date.UTC and getUTCDay avoid browser timezone differences.
         * The numeric weekday is independent of English/French labels.
         *
         * @param {HTMLElement} tab
         *   Program day tab.
         * @return {number|null}
         *   0 for Sunday through 6 for Saturday, or null if invalid.
         */
        const getWeekday = (tab) => {
          const dayKey = tab.dataset.programDayDate || '';
          const match = /^(\d{4})(\d{2})(\d{2})$/.exec(dayKey);

          if (!match) {
            return null;
          }

          const year = Number(match[1]);
          const month = Number(match[2]);
          const day = Number(match[3]);
          const date = new Date(Date.UTC(year, month - 1, day));

          // Prevent an invalid date from silently rolling into another day.
          if (
            date.getUTCFullYear() !== year ||
            date.getUTCMonth() !== month - 1 ||
            date.getUTCDate() !== day
          ) {
            return null;
          }

          return date.getUTCDay();
        };

        /**
         * Activate the selected day while keeping accessible tab states
         * and panel visibility in sync.
         *
         * @param {HTMLElement} tab
         *   Tab to activate.
         * @param {boolean} moveFocus
         *   Whether to focus the tab after keyboard navigation.
         */
        const activateTab = (tab, moveFocus = false) => {
          const panelId = tab.getAttribute('aria-controls');

          tabs.forEach((item) => {
            const isActive = item === tab;

            item.setAttribute(
              'aria-selected',
              isActive ? 'true' : 'false'
            );

            item.setAttribute('tabindex', isActive ? '0' : '-1');
            item.classList.toggle('active', isActive);
            item.classList.toggle('cool-bg', isActive);
            item.classList.toggle('text-white', isActive);
          });

          panels.forEach((panel) => {
            panel.hidden = panel.id !== panelId;
          });

          if (moveFocus) {
            tab.focus();
          }
        };

        /**
         * Default to the earliest scheduled day. If that first day is
         * Saturday or Sunday, prefer Monday when Monday has a program.
         */
        const firstWeekday = getWeekday(tabs[0]);
        const startsOnWeekend =
          firstWeekday === 0 || firstWeekday === 6;

        const mondayTab = startsOnWeekend
          ? tabs.find((tab) => getWeekday(tab) === 1)
          : null;

        const initialTab = mondayTab || tabs[0];
        activateTab(initialTab);

        /**
         * Complete progressive enhancement: ordinary headings and all
         * schedule days remain available without JavaScript.
         */
        program.querySelectorAll('[data-program-day-heading]')
          .forEach((heading) => {
            heading.hidden = true;
          });

        tabNavigation.hidden = false;

        // Mouse and touch activation.
        tabs.forEach((tab) => {
          tab.addEventListener('click', () => {
            activateTab(tab);
          });
        });

        // Horizontal tabs: arrow keys, Home and End activate and focus.
        tabs.forEach((tab, index) => {
          tab.addEventListener('keydown', (event) => {
            let targetIndex = null;

            switch (event.key) {
              case 'ArrowLeft':
                targetIndex = (index - 1 + tabs.length) % tabs.length;
                break;

              case 'ArrowRight':
                targetIndex = (index + 1) % tabs.length;
                break;

              case 'Home':
                targetIndex = 0;
                break;

              case 'End':
                targetIndex = tabs.length - 1;
                break;

              default:
                return;
            }

            event.preventDefault();
            activateTab(tabs[targetIndex], true);
          });
        });
      });
    },
  };
})(Drupal, once);
