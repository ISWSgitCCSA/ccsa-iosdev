(function ($, Drupal, Sortable) {
  Drupal.behaviors.media_folders_widget = {
    attach(context, settings) {
      $(
        once(
          'widget-board-folder-icon',
          '#media-folders.widget #board .folder a.folder-icon',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        const folder = $(this).parent('.folder');

        if (folder.hasClass('folder-folder')) {
          return;
        }

        if (settings.media_folders.selection_remaining === -1) {
          settings.media_folders.selection_remaining = 999;
        }

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
            if (
              index >= min &&
              index <= max &&
              $('#board .folder.selected').length <
                settings.media_folders.selection_remaining
            ) {
              $(this).addClass('selected');
              $(this).find('a.folder-icon').addClass('selected');
            }
          });
        } else if (
          $('#board .folder.selected').length <
          settings.media_folders.selection_remaining
        ) {
          $(this).toggleClass('selected');
          folder.toggleClass('selected');
        }

        if ($('#board .folder.selected').length === 1) {
          $('#board .folder.selected').ajaxGetPreview();
        } else {
          $('#preview').removeClass();
          $('#preview .preview-contents').html('');
        }

        if ($('#board .folder.selected').length) {
          $('#folders-details span.details').show();
        } else {
          $('#folders-details span.details').hide();
        }
      });

      $(
        once(
          'widget-board-back',
          '#media-folders.widget .top-bar .up-button',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        if ($(this).find('a').length) {
          const url = $(this).find('a').attr('href');
          $(this).ajaxWidgetLoad(
            url,
            settings.media_folders.state_parameters,
            settings.media_folders.widget_settings,
          );
        }
      });

      $(
        once(
          'widget-board-breadcrumb',
          '#media-folders.widget #navbar a, #media-folders.widget .top-bar .folder-breadcrumb a',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        const url = $(this).attr('href');
        $(this).ajaxWidgetLoad(
          url,
          settings.media_folders.state_parameters,
          settings.media_folders.widget_settings,
        );
      });

      $(
        once(
          'widget-navbar-expand',
          '#media-folders.widget #explorer #navbar > ul li > span',
          context,
        ),
      ).click(function (event) {
        $(this).parent().toggleClass('active-trail');
      });

      $(
        once(
          'widget-board-folder',
          '#media-folders.widget #board .folder',
          context,
        ),
      ).dblclick(function (event) {
        event.preventDefault();
        event.stopPropagation();

        if ($(this).find('a').hasClass('folder-icon-file')) {
          const fid = $('#board').attr('data-id');
          $(this).ajaxWidgetUpdate(
            $(this).attr('data-id'),
            settings.media_folders,
          );
        } else {
          event.preventDefault();
          event.stopPropagation();
          const url = $(this).find('a').attr('href');
          $(this).ajaxWidgetLoad(
            url,
            settings.media_folders.state_parameters,
            settings.media_folders.widget_settings,
          );
        }
      });

      $(
        once(
          'widget-board-submit',
          '#media-folders.widget #folders-details input',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        if ($('#board .folder.selected').length) {
          const toInsert = [];
          const selected = $('#board .folder.selected');
          selected.each(function () {
            toInsert.push($(this).attr('data-id'));
          });

          $(this).ajaxWidgetUpdate(toInsert.join(','), settings.media_folders);
        }
      });

      if (
        $('#media-folders.widget form.media-folders-search-form .form-text')
          .length
      ) {
        $(
          once(
            'widget-board-full-search',
            '#media-folders.widget form.media-folders-search-form',
            context,
          ),
        ).on('submit', function (event) {
          event.preventDefault();
          event.stopPropagation();

          const search = $(this).find('.form-text')[0].value;
          let mediaFoldersSettings = false;
          if (settings.hasOwnProperty('media_folders')) {
            mediaFoldersSettings = settings.media_folders;
          }
          $('#board').ajaxSearchRecursive(search, mediaFoldersSettings);
        });

        $(
          once(
            'widget-board-search',
            '#media-folders.widget form.media-folders-search-form .form-text',
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

        $(once('widget-board-folder-icon-drag', '#board .folder', context)).on(
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
      }
    },
  };

  Drupal.behaviors.MediaFoldersWidgetSortable = {
    attach(context) {
      const selection = context.querySelectorAll('.js-media-folders-selection');
      selection.forEach((widget) => {
        Sortable.create(widget, {
          draggable: '.js-media-folders-item',
          onEnd: () => {
            $(widget)
              .children()
              .each((index, child) => {
                $(child).find('.js-media-folders-item-weight')[0].value = index;
              });
          },
        });
      });
    },
  };

  Drupal.behaviors.MediaFoldersWidgetToggleWeight = {
    attach(context) {
      const strings = {
        show: Drupal.t('Show media item weights'),
        hide: Drupal.t('Hide media item weights'),
      };
      const mediaFoldersToggle = once(
        'media-folders-toggle',
        '.js-media-folders-widget-toggle-weight',
        context,
      );
      $(mediaFoldersToggle).on('click', (e) => {
        e.preventDefault();
        const $target = $(e.currentTarget);
        e.currentTarget.textContent = $target.hasClass('active')
          ? strings.show
          : strings.hide;
        $target
          .toggleClass('active')
          .closest('.js-media-folders-widget')
          .find('.js-media-folders-item-weight')
          .parent()
          .toggle();
      });
      mediaFoldersToggle.forEach((item) => {
        item.textContent = strings.show;
      });

      $(once('media-folders-toggle', '.js-media-folders-item-weight', context))
        .parent()
        .hide();
    },
  };

  Drupal.behaviors.MediaFoldersWidgetDisableButton = {
    attach(context) {
      once(
        'media-folders-disable',
        '.js-media-folders-open-button[data-disabled-focus="true"]',
        context,
      ).forEach((button) => {
        $(button).focus();
        setTimeout(() => {
          $(button).attr('disabled', 'disabled');
        }, 50);
      });
    },
  };
})(jQuery, Drupal, Sortable);
