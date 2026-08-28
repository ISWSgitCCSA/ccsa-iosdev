(function ($, Drupal) {
  Drupal.behaviors.media_folders = {
    attach(context) {
      const getQueryParam = function (url, key) {
        const queryStartPos = url.indexOf('?');
        if (queryStartPos === -1) {
          return;
        }
        const params = url.substring(queryStartPos + 1).split('&');
        for (let i = 0; i < params.length; i++) {
          const pairs = params[i].split('=');
          if (decodeURIComponent(pairs.shift()) === key) {
            return decodeURIComponent(pairs.join('='));
          }
        }
      };

      if ($(context)[0].nodeName === '#document') {
        $(once('admin-media-folders', '#media-folders', context)).each(
          function () {
            $(window).on('load', function () {
              const url = window.location.pathname;
              const state = {
                type: 'media-folders',
                url,
                page: document.title,
              };
              window.history.pushState(state, document.title, url);
            });
            $(window).on('popstate', function () {
              if (
                window.history.state &&
                window.history.state.type === 'media-folders'
              ) {
                $(this).ajaxLoad(window.history.state.url);
              }
            });
          },
        );

        if (
          $('.folder-breadcrumb').find('.folder-breadcrumb-inner').width() >=
          $('.folder-breadcrumb').width()
        ) {
          $('.folder-breadcrumb').addClass('overflow');
        } else {
          $('.folder-breadcrumb').removeClass('overflow');
        }

        $(once('admin-board-folder-icon-drag', '#board .folder', context)).on(
          'dragstart',
          function (event) {
            if (!$(this).hasClass('selected')) {
              $('#board .folder a.selected').removeClass('selected');
              $('#board .folder.selected').removeClass('selected');
              $(this).addClass('selected');
              $(this).find('a.folder-icon').addClass('selected');
            }
          },
        );

        $(
          once(
            'admin-board-folder-icon',
            '#board .folder a.folder-icon',
            context,
          ),
        ).click(function (event) {
          const folder = $(this).parent('.folder');

          if (!event.ctrlKey && !event.shiftKey && !event.metaKey) {
            $('#board .folder a.selected').not($(this)).removeClass('selected');
            $('#board .folder.selected').not(folder).removeClass('selected');
          }

          if (event.shiftKey && $('#board .folder.selected').length) {
            const first = $('#board .folder.selected').first();
            const firstIndex = $('#board .folder').index(first);
            const thisIndex = $('#board .folder').index(folder);
            const min = firstIndex < thisIndex ? firstIndex : thisIndex;
            const max = firstIndex < thisIndex ? thisIndex : firstIndex;

            $('#board .folder').each(function (index) {
              if (index >= min && index <= max) {
                $(this).addClass('selected');
                $(this).find('a.folder-icon').addClass('selected');
              }
            });
          } else {
            $(this).toggleClass('selected');
            folder.toggleClass('selected');
          }

          $('#folders-details span').html('');
          const count = $('#board .folder.selected').length;
          const itemCount = `<strong>${count}</strong> ${Drupal.t('items selected')}`;
          $('#folders-details span.details').html(itemCount);

          if ($('#board .folder.selected').length === 1) {
            $('#board .folder.selected').ajaxGetPreview();
          } else {
            $('#preview').removeClass();
            $('#preview .preview-contents').html('');
          }

          event.preventDefault();
          event.stopPropagation();
        });

        $(
          once(
            'admin-navbar-expand',
            '#explorer #navbar > ul li > span',
            context,
          ),
        ).click(function (event) {
          $(this).parent().toggleClass('active-trail');
        });

        $(
          once(
            'admin-board-back',
            '#media-folders .top-bar .up-button',
            context,
          ),
        ).click(function (event) {
          event.preventDefault();
          event.stopPropagation();

          if ($(this).find('a').length) {
            const url = $(this).find('a').attr('href');
            const state = {
              type: 'media-folders',
              url,
            };
            window.history.pushState(state, document.title, url);
            $(this).ajaxLoad(url);
          }
        });

        $(
          once(
            'admin-board-breadcrumb',
            '#navbar a, #media-folders .top-bar .folder-breadcrumb a',
            context,
          ),
        ).click(function (event) {
          event.preventDefault();
          event.stopPropagation();

          const url = $(this).attr('href');
          const state = {
            type: 'media-folders',
            url,
          };
          window.history.pushState(state, document.title, url);
          $(this).ajaxLoad(url);
        });

        $(once('admin-board-folder', '#board .folder', context)).dblclick(
          function (event) {
            event.preventDefault();
            event.stopPropagation();

            if ($(this).find('a').hasClass('folder-icon-file')) {
              const href = $(this).find('a').attr('href');
              window.open(href, '_blank');
            } else {
              const url = $(this).find('a').attr('href');
              const title = $(this).find('.title')[0].textContent;
              const state = {
                type: 'media-folders',
                url,
                page: title,
              };
              window.history.pushState(state, title, url);
              $(this).ajaxLoad(url);
            }
          },
        );

        $(
          once(
            'admin-board-buttons',
            '#media-folders .top-bar .buttons a',
            context,
          ),
        ).click(function (event) {
          event.preventDefault();
          event.stopPropagation();

          const url = $(this).attr('href');
          if (url) {
            const a = document.createElement('A');
            a.href = url;
            $('body').addClass('loading');
            $.ajax({
              url: `${a.pathname}/ajax`,
              type: 'GET',
              cache: false,
              success(result) {
                if (result.name === 'view-type') {
                  $('#media-folders').removeClass().addClass(result.value);
                }
                $(this).ajaxLoad(getQueryParam(url, 'destination'));
              },
              error(result) {
                $(this).showMessages(null, true);
              },
            });
          }
        });

        $(
          once(
            'admin-board-button-sorts',
            '#media-folders .top-bar .buttons a.sorts',
            context,
          ),
        ).click(function (event) {
          event.preventDefault();
          event.stopPropagation();

          $(this)
            .parents('.dropbutton-wrapper')
            .find('.dropbutton-toggle button')
            .trigger('click');
        });

        if ($('form.media-folders-search-form #edit-search').length) {
          $(
            once(
              'admin-board-search',
              'form.media-folders-search-form #edit-search',
              context,
            ),
          ).on('keyup', function () {
            if ($('#board .folder').length > 1) {
              const filter = $(this)[0].value.toUpperCase();
              let hasResults = false;
              $('#board .folder').each(function () {
                if (!$(this).hasClass('table-header')) {
                  const txtValue = $(this).find('span.title')[0].textContent;
                  if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    $(this).show();
                    hasResults = true;
                  } else {
                    $(this).hide();
                  }
                }
              });
              if (!hasResults) {
                if ($('#board .empty').length === 0) {
                  $('#board').append(
                    `<div class='empty'>${Drupal.t('No results')}</div>`,
                  );
                }
              } else {
                $('#board .empty').remove();
              }
            }
          });
        }

        $(window).on('dialogopen', (e, ui) => {
          Drupal.attachBehaviors();
        });
      }
    },
  };
})(jQuery, Drupal);
