(function ($, Drupal) {
  Drupal.behaviors.media_folders_editor = {
    attach(context, settings) {
      $(
        once(
          'editor-board-folder-icon',
          '#media-folders.ckeditor-widget #board .folder a.folder-icon',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        const folder = $(this).parent('.folder');

        if (folder.hasClass('folder-folder')) {
          return;
        }

        $('#board .folder a.selected').not($(this)).removeClass('selected');
        $('#board .folder.selected').not(folder).removeClass('selected');
        $(this).toggleClass('selected');
        folder.toggleClass('selected');

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
          'editor-board-back',
          '#media-folders.ckeditor-widget .top-bar .up-button',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        if ($(this).find('a').length) {
          const url = $(this).find('a').attr('href');
          $(this).ajaxEditorLoad(
            url,
            settings.media_folders.allowed_types,
            settings.media_folders.widget_settings,
          );
        }
      });

      $(
        once(
          'editor-board-breadcrumb',
          '#media-folders.ckeditor-widget #navbar a, #media-folders.ckeditor-widget .top-bar .folder-breadcrumb a',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        const url = $(this).attr('href');
        $(this).ajaxEditorLoad(
          url,
          settings.media_folders.allowed_types,
          settings.media_folders.widget_settings,
        );
      });

      $(
        once(
          'editor-navbar-expand',
          '#media-folders.ckeditor-widget #explorer #navbar > ul li > span',
          context,
        ),
      ).click(function (event) {
        $(this).parent().toggleClass('active-trail');
      });

      $(
        once(
          'editor-board-folder',
          '#media-folders.ckeditor-widget #board .folder',
          context,
        ),
      ).dblclick(function (event) {
        event.preventDefault();
        event.stopPropagation();

        if ($(this).find('a').hasClass('folder-icon-file')) {
          const uuid = $(this).attr('data-uuid');
          const values = {
            attributes: {
              'data-entity-type': 'media',
              'data-entity-uuid': uuid,
            },
          };
          $(window).trigger('editor:dialogsave', [values]);
          const $dialog = $('#drupal-modal');
          if ($dialog.length) {
            Drupal.dialog($dialog.get(0)).close();
          }
          $dialog.off('dialogButtonsChange');
        } else {
          event.preventDefault();
          event.stopPropagation();
          const url = $(this).find('a').attr('href');
          $(this).ajaxEditorLoad(
            url,
            settings.media_folders.allowed_types,
            settings.media_folders.widget_settings,
          );
        }
      });

      $(
        once(
          'editor-board-submit',
          '#media-folders.ckeditor-widget #folders-details input',
          context,
        ),
      ).click(function (event) {
        event.preventDefault();
        event.stopPropagation();

        if ($('#board .folder.selected').length) {
          const uuid = $('#board .folder.selected').attr('data-uuid');
          const values = {
            attributes: {
              'data-entity-type': 'media',
              'data-entity-uuid': uuid,
            },
          };
          $(window).trigger('editor:dialogsave', [values]);
        }

        const $dialog = $('#drupal-modal');
        if ($dialog.length) {
          Drupal.dialog($dialog.get(0)).close();
        }
        $dialog.off('dialogButtonsChange');
      });

      if ($('form.media-folders-search-form .form-text').length) {
        $(
          once(
            'editor-board-search',
            'form.media-folders-search-form .form-text',
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
    },
  };
})(jQuery, Drupal);
