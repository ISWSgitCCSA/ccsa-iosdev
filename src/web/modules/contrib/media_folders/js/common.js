(function ($, Drupal) {
  function handleAjaxError(context) {
    context.showMessages(null, true);
    $('body').removeClass('loading');
  }

  $.fn.extend({
    showMessages(messagesArray, error = false) {
      const messages = new Drupal.Message($('#folders-messages')[0]);
      if (error) {
        messages.add(Drupal.t('An error occurred'), {
          type: 'error',
        });
      } else {
        for (let i = 0; i < messagesArray.length; i++) {
          messages.add(messagesArray[i].message, {
            type: messagesArray[i].type,
          });
        }
      }
    },
  });

  $.fn.extend({
    updateFileExplorer(result) {
      if ($(this).find('.folder-form form').length) {
        $(this).find('.folder-form .form-text')[0].value = '';
      }

      const board = $(this).find('#board');
      if (result.hasOwnProperty('folders')) {
        board.html(result.folders);
      }

      if (result.hasOwnProperty('board_class')) {
        board.removeClass();
        board.addClass(result.board_class);
      }

      if (result.hasOwnProperty('fid')) {
        board.attr('data-id', result.fid);
      }

      if (result.hasOwnProperty('navbar')) {
        $(this).find('#navbar').html(result.navbar);
      }

      if ($(this).hasClass('widget')) {
        $(this).find('#folders-details span.details').hide();
      } else {
        $(this).find('#folders-details span.details').html('');
      }

      $(this).find('#preview').removeClass();
      $(this).find('#preview .preview-contents').html('');

      if (result.hasOwnProperty('breadcrumb')) {
        $(this).find('.folder-breadcrumb-inner').html(result.breadcrumb);
      }

      if (result.hasOwnProperty('actions')) {
        $(this).find('.operations').html(result.actions);
      } else {
        $(this).find('.operations').html('');
      }

      if (result.hasOwnProperty('actions_raw')) {
        $(this).find('> .actions').html(result.actions_raw);
      } else {
        $(this).find('> .actions').html('');
      }

      if (result.hasOwnProperty('buttons')) {
        $(this).find('.buttons').html(result.buttons);
      } else if (result.hasOwnProperty('upload_form')) {
        $(this).find('.buttons').html(result.upload_form);
      } else {
        $(this).find('.buttons').html('');
      }

      if (result.hasOwnProperty('up_button')) {
        $(this).find('.up-button').html(result.up_button);
      } else {
        $(this).find('.up-button').html('');
      }

      if (result.hasOwnProperty('title')) {
        $('h1.page-title').html(result.title);
      }

      $('body').removeClass('loading');
      Drupal.attachBehaviors();
    },
  });

  $.fn.extend({
    ajaxSearchRecursive(search, mediaFoldersSettings) {
      const fid = $(this).attr('data-id');
      $('body').addClass('loading');
      let stateParameters = null;
      if (
        typeof mediaFoldersSettings !== 'undefined' &&
        mediaFoldersSettings.hasOwnProperty('state_parameters')
      ) {
        stateParameters = mediaFoldersSettings.state_parameters;
      }
      $.ajax({
        url: `/admin/content/media-folders/${fid}/search/ajax`,
        type: 'POST',
        data: { request: search, stateParameters },
        success(result) {
          $('#media-folders').updateFileExplorer(result);
        },
        error(result) {
          handleAjaxError($(this));
        },
      });
    },
  });

  $.fn.extend({
    ajaxLoadMore(widgetSettings, stateParameters) {
      let page = 0;
      if (typeof $(this).attr('data-page') !== 'undefined') {
        page = $(this).attr('data-page');
      }
      const button = $(this).find('.load-more a');
      $('body').addClass('loading');
      $.ajax({
        url: button.attr('href'),
        type: 'POST',
        data: { page, widgetSettings, stateParameters },
        success(result) {
          $('#board .load-more').remove();
          $('#board').attr('data-page', result.page);
          $('#board').append(result.files);
          $('body').removeClass('loading');
          Drupal.attachBehaviors();
        },
        error(result) {
          handleAjaxError($(this));
        },
      });
    },
  });

  $.fn.extend({
    ajaxGetPreview() {
      const folder = $(this).find('a.folder-icon');
      if ($(this).hasClass('folder-file')) {
        const mid = $(this).attr('data-id');
        const dataExtension = folder.attr('data-ext');
        const dataSize = folder.attr('data-size');
        const details = `<div>${dataExtension} ${Drupal.t('file')}</div><div>${dataSize}</div>`;

        $.ajax({
          url: `/admin/content/media-folders/${mid}/preview/ajax`,
          type: 'GET',
          success(result) {
            $('#preview .preview-contents').html('');
            $('#preview').addClass('open');
            setTimeout(function () {
              $('#preview .preview-contents').html(
                `${result.render}${details}`,
              );
            }, 300);
          },
        });
      } else if ($(this).hasClass('folder-folder')) {
        setTimeout(function () {
          const text = folder.find('span.title')[0].textContent;
          const desc = folder.attr('title');
          const dataCount = folder.attr('data-count');
          const details = `<div>${text}</div><div>${desc}</div><div>${dataCount} ${Drupal.t('items')}</div>`;
          $('#preview .preview-contents').html('');
          $('#preview').addClass('open');
          setTimeout(function () {
            $('#preview .preview-contents').html(`${details}`);
          }, 300);
        }, 500);
      }
    },
  });

  $.fn.extend({
    ajaxLoad(url) {
      $('body').addClass('loading');
      $.ajax({
        url: `${url}/ajax`,
        type: 'GET',
        success(result) {
          $('#media-folders').updateFileExplorer(result);
        },
        error(result) {
          handleAjaxError($(this));
        },
      });
    },
  });

  $.fn.extend({
    ajaxWidgetLoad(url, stateParameters, widgetSettings) {
      const widgetId = stateParameters.field_name;
      const bundle = stateParameters.bundle;
      const entityTypeId = stateParameters.entity_type_id;

      $('body').addClass('loading');
      $.ajax({
        url: `${url}/widget/${entityTypeId}/${bundle}/${widgetId}/ajax`,
        type: 'POST',
        data: { request: widgetSettings },
        success(result) {
          $('#media-folders').updateFileExplorer(result);
        },
        error(result) {
          handleAjaxError($(this));
        },
      });
    },
  });

  $.fn.extend({
    ajaxEditorLoad(url, allowedTypes, widgetSettings) {
      $('body').addClass('loading');
      $.ajax({
        url: `${url}/editor/ajax`,
        type: 'POST',
        data: { request: { allowedTypes, widgetSettings } },
        success(result) {
          $('#media-folders').updateFileExplorer(result);
        },
        error(result) {
          handleAjaxError($(this));
        },
      });
    },
  });

  $.fn.extend({
    ajaxWidgetUpdate(fid, parameters) {
      $(
        `input[data-media-folders-widget-value="${parameters.state_parameters.field_widget_id}"]`,
      )[0].value = fid;
      $(
        `:submit[data-media-folders-widget-update="${parameters.state_parameters.field_widget_id}"]`,
      ).trigger('mousedown');
      Drupal.dialog(document.getElementById('drupal-modal')).close();
    },
  });

  $.fn.extend({
    fileDrop(options) {
      const defaults = {
        callback: null,
      };
      options = $.extend(defaults, options);
      return this.each(function () {
        const $this = $(this);

        $this.on('dragover dragenter', function (event) {
          event.stopPropagation();
          event.preventDefault();
          if (!$('#board').hasClass('dragging-started')) {
            $this.addClass('droppable upload-droppable');
          }
        });

        $this.on('dragleave', function (event) {
          event.stopPropagation();
          event.preventDefault();

          if (!$('#board').hasClass('dragging-started')) {
            if ($(this).hasClass('folder-folder')) {
              $this.removeClass('droppable upload-droppable');
            } else {
              const relatedTarget = $(event.relatedTarget);
              if (relatedTarget.attr('id') !== 'board') {
                if (!relatedTarget.parents('#board').length) {
                  $this.removeClass('droppable upload-droppable');
                }
              }
            }
          }
        });

        $this.on('drop', function (event) {
          event.stopPropagation();
          event.preventDefault();
          $this.removeClass('droppable upload-droppable');

          if ($(this).hasClass('not-droppable')) {
            return false;
          }

          if (typeof event.originalEvent.dataTransfer !== 'undefined') {
            const fid = $this.attr('data-id');
            const files = event.originalEvent.dataTransfer.files || [];
            event.originalEvent.dataTransfer.dropEffect = 'copy';

            if (options.callback) {
              options.callback(fid, files);
            }
          }

          return false;
        });
      });
    },
  });

  $.fn.extend({
    readFile(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        const name = file.name;
        const size = file.size;
        const type = file.type;
        reader.onload = function (evt) {
          const obj = {
            name,
            size,
            type,
            contents: evt.target.result,
          };
          resolve(obj);
        };

        reader.onerror = function (evt) {
          const obj = {
            name: null,
            size: null,
            type: null,
            contents: null,
          };
          resolve(obj);
        };

        reader.readAsDataURL(file);
      });
    },
  });

  $.fn.extend({
    readFiles(files) {
      return new Promise((resolve, reject) => {
        const uploadFiles = [];
        const filesLength = files.length;
        for (let i = 0; i < files.length; i++) {
          $(this)
            .readFile(files[i])
            .then((readerRs) => {
              if (readerRs) {
                uploadFiles.push(readerRs);
                if (files.length === uploadFiles.length) {
                  resolve(uploadFiles);
                }
              }
            })
            .catch((error) => {
              uploadFiles.push({});
            });
        }
      });
    },
  });

  $.fn.extend({
    ajaxUpload(fid, files) {
      if (files.length) {
        $('body').addClass('loading');
        $(this)
          .readFiles(files)
          .then((readerRs) => {
            if (readerRs) {
              $.ajax({
                url: `/admin/content/media-folders/${fid}/upload-file/ajax`,
                type: 'POST',
                data: { request: readerRs },
                cache: false,
                success(result) {
                  if (result.messages.length) {
                    $(this).showMessages(result.messages);
                  }

                  if (result.files.length) {
                    const ajaxSettings = {
                      url: `/admin/content/media-folders/${fid}/add-file`,
                      dialogType: 'modal',
                      dialog: {
                        width: 800,
                        files: result.files,
                      },
                    };
                    Drupal.ajax(ajaxSettings).execute();
                    $('#board').removeClass('droppable');
                    $('body').removeClass('loading');
                  }
                },
                error(result) {
                  $(this).showMessages(null, true);
                  $('body').removeClass('loading');
                },
              });
            }
          })
          .catch((error) => {
            console.error('Error reading files:', error);
          });
      }
    },
  });

  $.fn.extend({
    ajaxWidgetUpload(fid, files, settings) {
      if (files.length) {
        $('body').addClass('loading');
        $(this)
          .readFiles(files)
          .then((readerRs) => {
            if (readerRs) {
              $.ajax({
                url: `/admin/content/media-folders/${fid}/upload-file/ajax`,
                type: 'POST',
                data: {
                  request: readerRs,
                  parameters: settings.media_folders.state_parameters,
                },
                cache: false,
                success(result) {
                  if (result.messages.length) {
                    $(this).showMessages(result.messages);
                  }

                  if (result.files.length) {
                    const params = settings.media_folders.state_parameters;
                    const ajaxSettings = {
                      url: `/admin/content/media-folders/${fid}/widget/${params.entity_type_id}/${params.bundle}/${params.field_widget_id}/add-file`,
                      dialogType: 'dialog',
                      dialog: {
                        width: 800,
                        files: result.files,
                      },
                    };
                    Drupal.ajax(ajaxSettings).execute();
                    $('#board').removeClass('droppable');
                    $('body').removeClass('loading');
                  }
                },
                error(result) {
                  $(this).showMessages(null, true);
                  $('body').removeClass('loading');
                },
              });
            }
          })
          .catch((error) => {
            console.error('Error reading files:', error);
          });
      }
    },
  });

  $.fn.extend({
    ajaxMove(fid, toMove) {
      $('body').addClass('loading');
      $.ajax({
        url: `/admin/content/media-folders/${fid}/move-into/ajax`,
        type: 'POST',
        data: { objects: toMove },
        cache: false,
        success(result) {
          if (result.messages.length) {
            $(this).showMessages(result.messages);
          }
          if (result.messages[0].type === 'status') {
            const url =
              parseInt(fid, 10) === 0
                ? '/admin/content/media-folders'
                : `/admin/content/media-folders/${fid}`;
            const state = {
              type: 'media-folders',
              url,
            };
            window.history.pushState(state, document.title, url);
            $(this).ajaxLoad(url);
          } else {
            const url = window.location.pathname;
            $(this).ajaxLoad(url);
          }
        },
        error(result) {
          handleAjaxError($(this));
        },
      });
    },
  });

  Drupal.behaviors.folders_messages = {
    attach(context, settings) {
      $(
        once(
          'folders_messages__wrapper_close',
          '#folders-messages .messages',
          context,
        ),
      ).each(function () {
        $(this).append('<span class="close"></span>');
        $(this).append('<div class="progress"></div>');
      });

      $(
        once('folders_messages', '#folders-messages .messages .close', context),
      ).on('click', function () {
        $(this).parent('.messages').removeClass('active');
        $(this).parent('.messages').find('.progress').removeClass('active');
        setTimeout(() => {
          $(this).parent('.messages').remove();
        }, 600);
      });

      $(
        once(
          'folders_messages__wrapper_toast',
          '#folders-messages .messages',
          context,
        ),
      ).each(function (index) {
        setTimeout(() => {
          $(this).addClass('active');
          $(this).find('.progress').addClass('active');
        }, 400 * index);
        setTimeout(
          () => {
            $(this).removeClass('active');
            $(this).find('.progress').removeClass('active');
            setTimeout(() => {
              $(this).remove();
            }, 600);
          },
          400 * index + 15000,
        );
      });

      if ($('#board .load-more a').length) {
        $(once('admin-board-load-more', '#board .load-more a', context)).on(
          'click',
          function (event) {
            event.preventDefault();
            event.stopPropagation();

            let widgetSettings = null;
            if (
              typeof settings.media_folders !== 'undefined' &&
              typeof settings.media_folders.widget_settings !== 'undefined'
            ) {
              widgetSettings = settings.media_folders.widget_settings;
            }

            let stateParameters = null;
            if (
              typeof settings.media_folders !== 'undefined' &&
              typeof settings.media_folders.state_parameters !== 'undefined'
            ) {
              stateParameters = settings.media_folders.state_parameters;
            }

            $('#board').ajaxLoadMore(widgetSettings, stateParameters);
          },
        );
      }
    },
  };
})(jQuery, Drupal);
