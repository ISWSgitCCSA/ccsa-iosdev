(function (Drupal, once) {
  'use strict';

  let instanceCounter = 0;

  /**
   * Enhances grouped homepage program days into an accessible tab interface.
   *
   * The Twig output is fully usable without JavaScript. This behavior only
   * changes presentation after the grouped schedule already exists in the DOM.
   */
  Drupal.behaviors.iosHomepageProgramPreview = {
    attach(context) {
      once(
        'ios-homepage-program-preview',
        '.view-homepage-program-preview .view-content',
        context
      ).forEach((viewContent) => {
        const dayPanels = Array.from(
          viewContent.querySelectorAll('.ios-program-day')
        ).filter((panel) => panel.closest('.view-content') === viewContent);

        /*
         * One day does not need a tab interface. Keep the semantic fallback
         * heading and list exactly as rendered by Twig.
         */
        if (dayPanels.length < 2) {
          return;
        }

        instanceCounter += 1;
        const instanceId = `homepage-program-preview-${instanceCounter}`;

        /*
         * Prefer Monday as the initial homepage preview day when it exists.
         * Fall back to the first available conference day otherwise.
         */
        const defaultIndex = (() => {
          const mondayIndex = dayPanels.findIndex((panel) => {
            const timeElement = panel.querySelector('time[datetime]');

            if (!timeElement) {
              return false;
            }

            const date = new Date(timeElement.getAttribute('datetime'));

            return !Number.isNaN(date.getTime()) && date.getDay() === 1;
          });

          return mondayIndex >= 0 ? mondayIndex : 0;
        })();

        const tabList = document.createElement('div');
        tabList.className = [
          'nav',
          'nav-underline',
          'flex-nowrap',
          'justify-content-start',
          'gap-2',
          'border-bottom',
          'px-3',
          'overflow-auto'
        ].join(' ');
        tabList.id = `${instanceId}-tabs`;
        tabList.setAttribute('role', 'tablist');
        tabList.setAttribute('aria-label', Drupal.t('Program dates'));

        const tabs = [];

        /**
         * Builds a compact two-line tab label from the first machine-readable
         * datetime in the day panel. Falls back to the rendered group title if
         * a <time datetime> element is not available.
         */
        const getTabLabel = (panel, fallbackLabel) => {
          const timeElement = panel.querySelector('time[datetime]');

          if (!timeElement) {
            return {
              primary: fallbackLabel,
              secondary: ''
            };
          }

          const date = new Date(timeElement.getAttribute('datetime'));

          if (Number.isNaN(date.getTime())) {
            return {
              primary: fallbackLabel,
              secondary: ''
            };
          }

          const locale = document.documentElement.lang || 'en-CA';

          return {
            primary: new Intl.DateTimeFormat(locale, {
              month: 'short',
              day: 'numeric'
            }).format(date),
            secondary: new Intl.DateTimeFormat(locale, {
              weekday: 'short'
            }).format(date)
          };
        };

        dayPanels.forEach((panel, index) => {
          const heading = panel.querySelector('.ios-program-day__heading');
          const headingWrapper = panel.querySelector(
            '.ios-program-day__fallback-heading'
          );
          const fallbackLabel = heading
            ? heading.textContent.trim()
            : Drupal.t('Program day @number', { '@number': index + 1 });
          const label = getTabLabel(panel, fallbackLabel);

          const tabId = `${instanceId}-tab-${index + 1}`;
          const panelId = `${instanceId}-panel-${index + 1}`;

          const tab = document.createElement('button');
          tab.type = 'button';
          tab.className = [
            'nav-link',
            'cool-dark',
            'px-3',
            'py-2',
            index === defaultIndex ? 'active' : ''
          ].filter(Boolean).join(' ');
          tab.id = tabId;
          tab.setAttribute('role', 'tab');
          tab.setAttribute('aria-controls', panelId);
          tab.setAttribute(
            'aria-selected',
            index === defaultIndex ? 'true' : 'false'
          );
          tab.tabIndex = index === defaultIndex ? 0 : -1;

          const primary = document.createElement('span');
          primary.className = 'd-block fw-semibold lh-1';
          primary.textContent = label.primary;
          tab.appendChild(primary);

          if (label.secondary) {
            const secondary = document.createElement('small');
            secondary.className = 'd-block mt-1 fw-normal lh-1';
            secondary.textContent = label.secondary;
            tab.appendChild(secondary);
          }

          panel.id = panelId;
          panel.setAttribute('role', 'tabpanel');
          panel.setAttribute('aria-labelledby', tabId);

          /*
           * Keep a busy conference day from making the homepage preview
           * excessively tall. The tab navigation remains fixed above while
           * the active day's schedule scrolls vertically. Making the panel
           * focusable also lets keyboard users scroll it directly.
           */
          panel.classList.add('overflow-auto', 'pe-2');
          panel.style.maxBlockSize = '28rem';
          panel.style.overscrollBehavior = 'contain';
          panel.tabIndex = 0;

          if (index !== defaultIndex) {
            panel.hidden = true;
          }

          /*
           * Once tabs exist, the tab itself names the panel. Hide the fallback
           * heading to avoid presenting the same date twice.
           */
          if (headingWrapper) {
            headingWrapper.hidden = true;
          }

          tabs.push(tab);
          tabList.appendChild(tab);
        });

        const activateTab = (newIndex, moveFocus = false) => {
          tabs.forEach((tab, index) => {
            const isActive = index === newIndex;

            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.tabIndex = isActive ? 0 : -1;
            dayPanels[index].hidden = !isActive;
          });

          if (moveFocus) {
            tabs[newIndex].focus();
          }
        };

        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => {
            activateTab(index, false);
          });

          tab.addEventListener('keydown', (event) => {
            let targetIndex = index;

            switch (event.key) {
              case 'ArrowLeft':
                targetIndex = index === 0 ? tabs.length - 1 : index - 1;
                break;

              case 'ArrowRight':
                targetIndex = index === tabs.length - 1 ? 0 : index + 1;
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
            activateTab(targetIndex, true);
          });
        });

        viewContent.insertBefore(tabList, dayPanels[0]);
      });
    }
  };
})(Drupal, once);
