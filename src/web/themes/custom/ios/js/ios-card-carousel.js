(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.iosCardCarousel = {
    attach(context) {
      once('ios-card-carousel', '[data-ios-card-carousel]', context).forEach((carousel) => {
        const viewport = carousel.querySelector('[data-ios-card-carousel-viewport]');
        const track = carousel.querySelector('[data-ios-card-carousel-track]');
        const items = Array.from(carousel.querySelectorAll('[data-ios-card-carousel-item]'));
        const previousButton = carousel.querySelector('[data-ios-card-carousel-prev]');
        const nextButton = carousel.querySelector('[data-ios-card-carousel-next]');
        const indicators = carousel.querySelector('[data-ios-card-carousel-indicators]');
        const status = carousel.querySelector('[data-ios-card-carousel-status]');

        if (!viewport || !track || !items.length || !previousButton || !nextButton || !indicators) {
          return;
        }

        let currentPage = 0;
        let itemsPerPage = 1;
        let pageCount = 1;
        let resizeTimer = null;
        let touchStartX = null;
        let touchStartY = null;
        const itemLabel = carousel.dataset.iosCardCarouselLabel || Drupal.t('items');

        const getItemsPerPage = () => {
          if (window.matchMedia('(min-width: 1200px)').matches) {
            return 3;
          }

          if (window.matchMedia('(min-width: 768px)').matches) {
            return 2;
          }

          return 1;
        };

        const getFirstItemIndex = () => currentPage * itemsPerPage;

        const updateStatus = () => {
          if (!status) {
            return;
          }

          const first = getFirstItemIndex() + 1;
          const last = Math.min(first + itemsPerPage - 1, items.length);

          status.textContent = Drupal.t('Showing @label @first to @last of @total', {
            '@label': itemLabel,
            '@first': first,
            '@last': last,
            '@total': items.length,
          });
        };

        const updateItemVisibility = () => {
          const firstVisible = getFirstItemIndex();
          const lastVisible = Math.min(firstVisible + itemsPerPage, items.length);

          items.forEach((item, index) => {
            const visible = index >= firstVisible && index < lastVisible;

            if (visible) {
              item.removeAttribute('aria-hidden');
              item.removeAttribute('inert');
            }
            else {
              if (item.contains(document.activeElement)) {
                nextButton.focus();
              }

              item.setAttribute('aria-hidden', 'true');
              item.setAttribute('inert', '');
            }
          });
        };

        const updateControls = () => {
          const atStart = currentPage === 0;
          const atEnd = currentPage >= pageCount - 1;

          previousButton.disabled = atStart;
          previousButton.setAttribute('aria-disabled', atStart ? 'true' : 'false');

          nextButton.disabled = atEnd;
          nextButton.setAttribute('aria-disabled', atEnd ? 'true' : 'false');
        };

        const updateIndicators = () => {
          indicators.querySelectorAll('button').forEach((button, index) => {
            const active = index === currentPage;

            button.classList.toggle('active', active);

            if (active) {
              button.setAttribute('aria-current', 'true');
            }
            else {
              button.removeAttribute('aria-current');
            }
          });
        };

        const moveTrack = () => {
          const firstItem = items[getFirstItemIndex()];

          if (!firstItem) {
            return;
          }

          track.style.transform = `translateX(${-firstItem.offsetLeft}px)`;
        };

        const goToPage = (page, announce = true) => {
          currentPage = Math.max(0, Math.min(page, pageCount - 1));

          moveTrack();
          updateItemVisibility();
          updateControls();
          updateIndicators();

          if (announce) {
            updateStatus();
          }
        };

        const buildIndicators = () => {
          indicators.replaceChildren();

          if (pageCount <= 1) {
            indicators.hidden = true;
            return;
          }

          indicators.hidden = false;

          for (let index = 0; index < pageCount; index += 1) {
            const button = document.createElement('button');

            button.type = 'button';
            button.setAttribute('aria-label', Drupal.t('Show @label page @page of @total', {
              '@label': itemLabel,
              '@page': index + 1,
              '@total': pageCount,
            }));
            button.addEventListener('click', () => goToPage(index));

            indicators.appendChild(button);
          }
        };

        const configure = (preserveIndex = true) => {
          const previousItemsPerPage = itemsPerPage;
          const previousFirstIndex = currentPage * previousItemsPerPage;

          itemsPerPage = getItemsPerPage();
          pageCount = Math.max(1, Math.ceil(items.length / itemsPerPage));

          carousel.style.setProperty('--ios-card-carousel-columns', itemsPerPage);

          if (preserveIndex) {
            currentPage = Math.min(
              Math.floor(previousFirstIndex / itemsPerPage),
              pageCount - 1,
            );
          }
          else {
            currentPage = 0;
          }

          buildIndicators();
          goToPage(currentPage, false);
          updateStatus();
        };

        previousButton.addEventListener('click', () => {
          goToPage(currentPage - 1);
        });

        nextButton.addEventListener('click', () => {
          goToPage(currentPage + 1);
        });

        viewport.addEventListener('touchstart', (event) => {
          if (event.touches.length !== 1) {
            return;
          }

          touchStartX = event.touches[0].clientX;
          touchStartY = event.touches[0].clientY;
        }, { passive: true });

        viewport.addEventListener('touchend', (event) => {
          if (touchStartX === null || touchStartY === null || !event.changedTouches.length) {
            return;
          }

          const deltaX = event.changedTouches[0].clientX - touchStartX;
          const deltaY = event.changedTouches[0].clientY - touchStartY;

          touchStartX = null;
          touchStartY = null;

          if (Math.abs(deltaX) < 45 || Math.abs(deltaX) <= Math.abs(deltaY)) {
            return;
          }

          if (deltaX < 0) {
            goToPage(currentPage + 1);
          }
          else {
            goToPage(currentPage - 1);
          }
        }, { passive: true });

        window.addEventListener('resize', () => {
          window.clearTimeout(resizeTimer);

          resizeTimer = window.setTimeout(() => {
            configure(true);
          }, 120);
        });

        if (typeof ResizeObserver !== 'undefined') {
          const observer = new ResizeObserver(() => {
            moveTrack();
          });

          observer.observe(viewport);
        }

        configure(false);
      });
    },
  };
})(Drupal, once);
