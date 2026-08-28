(function ($, Drupal) {
  Drupal.behaviors.media_folders_file_upload = {
    attach(context, settings) {
      $(
        once(
          'media-folders-upload',
          '#media-folders:not(.widget):not(.ckeditor-widget) #board:not(.not-droppable), #media-folders:not(.widget):not(.ckeditor-widget) .folder.folder-folder:not(.not-droppable)',
          context,
        ),
      ).each(function () {
        $(this).fileDrop({
          callback(fid, files) {
            $(this).ajaxUpload(fid, files);
          },
        });
      });

      $(
        once(
          'media-folders-widget-upload',
          '#media-folders.widget #board:not(.not-droppable), #media-folders.widget .folder.folder-folder:not(.not-droppable)',
          context,
        ),
      ).each(function () {
        $(this).fileDrop({
          callback(fid, files) {
            $(this).ajaxWidgetUpload(fid, files, settings);
          },
        });
      });
    },
  };
})(jQuery, Drupal);
