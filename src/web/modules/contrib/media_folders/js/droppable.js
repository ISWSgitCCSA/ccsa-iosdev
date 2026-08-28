(function ($, Drupal, Sortable) {
  Drupal.behaviors.media_folder_droppable = {
    attach(context) {
      $(once('board-draggable', '#board:not(.search-results)', context)).each(
        function () {
          Sortable.create(this, {
            group: {
              name: 'shared',
              pull: 'clone',
              revertClone: true,
            },
            sort: false,
            dataIdAttr: 'data-sort-id',
            dragClass: 'ui-dragging',
            onStart: (evt) => Drupal.onStartAddClasses(),
            onEnd: (evt) => Drupal.onEndRemoveClasses(),
            onMove: (evt, originalEvent) => Drupal.onMoveChangeClasses(evt),
            onAdd: (evt) => Drupal.onAddRemoveClasses(evt),
            onClone: (evt) => Drupal.onCloneRemoveSelected(evt),
          });
        },
      );

      $(
        once(
          'folders-droppable',
          '#board:not(.search-results) .folder',
          context,
        ),
      ).each(function () {
        if ($(this).hasClass('folder-folder')) {
          Sortable.create(this, {
            group: {
              name: 'shared',
              pull: 'clone',
              revertClone: true,
            },
            sort: false,
            dataIdAttr: 'data-sort-id',
            dragClass: 'ui-dragging',
            onStart: (evt) => Drupal.onStartAddClasses(),
            onEnd: (evt) => Drupal.onEndRemoveClasses(),
            onMove: (evt, originalEvent) => Drupal.onMoveChangeClasses(evt),
            onAdd: (evt) => Drupal.onAddMove(evt),
          });
        }
      });

      $(once('navbar-droppable', '#navbar .navbar-folder', context)).each(
        function () {
          Sortable.create(this, {
            group: {
              name: 'shared',
              pull: false,
            },
            sort: false,
            dataIdAttr: 'data-sort-id',
            onStart(evt) {
              $('#board').addClass('dragging-started');
            },
            onEnd(evt) {
              $('#board').removeClass('dragging-started');
            },
            onAdd: (evt) => Drupal.onAddMove(evt),
          });
        },
      );
    },
  };

  Drupal.onAddMove = function (evt) {
    const toMove = [];
    const selected = $('#board .folder.selected');
    console.log(selected);
    selected.each(function () {
      const mid = $(this).attr('data-id');
      const type = $(this).hasClass('folder-file') ? 'file' : 'folder';
      toMove.push({ mid, type });
    });
    const target = $(evt.to);
    const fid = target.attr('data-id');
    $(evt.item).remove();
    $('#board').removeClass('dragging-started');
    $('#board .folder').removeClass('droppable move-droppable');

    if (typeof fid !== 'undefined' && !target.hasClass('not-droppable')) {
      $(this).ajaxMove(fid, toMove);
    }
  };

  Drupal.onAddRemoveClasses = function (evt) {
    $(evt.item).remove();
    $('#board').removeClass('dragging-started');
    $('#board .folder').removeClass('droppable move-droppable');
    $('#navbar .navbar-folder').removeClass('droppable move-droppable');
    $('.ui-droppable-hover').removeClass('ui-droppable-hover');
  };

  Drupal.onStartAddClasses = function () {
    $('#board').addClass('dragging-started');
    $('#board .folder').addClass('droppable move-droppable');
    $('#navbar .navbar-folder').addClass('droppable move-droppable');
  };

  Drupal.onEndRemoveClasses = function () {
    $('#board').removeClass('dragging-started');
    $('#board .folder').removeClass('droppable move-droppable');
    $('#navbar .navbar-folder').removeClass('droppable move-droppable');
    $('.ui-droppable-hover').removeClass('ui-droppable-hover');
  };

  Drupal.onMoveChangeClasses = function (evt) {
    $('.ui-droppable-hover').removeClass('ui-droppable-hover');
    const folder = $(evt.related).parent('.folder-folder');
    if (folder.length) {
      folder.addClass('ui-droppable-hover');
    }
    const navbarFolder = $(evt.related).parent('.navbar-folder');
    if (navbarFolder.length) {
      navbarFolder.addClass('ui-droppable-hover');
    }
  };

  Drupal.onCloneRemoveSelected = function (evt) {
    const origEl = evt.item;
    $(origEl).removeClass('selected');
    $(origEl).find('a').removeClass('selected');
  };
})(jQuery, Drupal, Sortable);
