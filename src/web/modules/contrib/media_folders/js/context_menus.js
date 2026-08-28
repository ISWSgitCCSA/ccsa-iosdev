(function ($, Drupal) {
  let taskItemInContext;
  let clickCoords;
  let clickCoordsX;
  let clickCoordsY;
  let menuWidth;
  let menuHeight;
  let windowWidth;
  let windowHeight;
  let menuState = 0;

  function clickInsideElement(e, className) {
    let el = e.srcElement || e.target;
    if (el.classList.contains(className)) {
      return el;
    }

    while (true) {
      el = el.parentNode;
      if (!el) {
        break;
      }
      if (el.classList && el.classList.contains(className)) {
        return el;
      }
    }

    return false;
  }

  function clickInsideIdElement(e, id) {
    let el = e.srcElement || e.target;
    if (el.id === id) {
      return el;
    }

    while (true) {
      el = el.parentNode;
      if (!el) {
        break;
      }

      if (el.id && el.id === id) {
        return el;
      }
    }

    return false;
  }

  function toggleMenuOn(taskItemInContext, menu) {
    if (menuState !== 1 && typeof menu !== 'undefined') {
      menuState = 1;
      if ($(taskItemInContext).hasClass('folder')) {
        $('.folder, .folder a').removeClass('selected');
        $(taskItemInContext).addClass('selected');
        $(taskItemInContext).find('a').addClass('selected');
      }

      menu.classList.add('context-menu--active');
    }
  }

  function toggleMenuOff() {
    if (menuState !== 0) {
      menuState = 0;
      $('.folder, .folder a').removeClass('selected');
      $('span.actions').removeClass('context-menu--active');
    }
  }

  function getPosition(e) {
    if (!e) {
      e = window.event;
    }

    return {
      x: e.clientX,
      y: e.clientY,
    };
  }

  function positionMenu(e, taskItemInContext, menu) {
    if (typeof menu !== 'undefined') {
      clickCoords = getPosition(e);
      clickCoordsX = clickCoords.x;
      clickCoordsY = clickCoords.y;
      menuWidth = menu.offsetWidth + 4;
      menuHeight = menu.offsetHeight + 4;

      windowWidth = window.innerWidth;
      windowHeight = window.innerHeight;

      if (windowWidth - clickCoordsX < menuWidth) {
        const left = parseInt(windowWidth - menuWidth, 10);
        menu.style.left = `${left}px`;
      } else {
        const left = parseInt(clickCoordsX, 10);
        menu.style.left = `${left}px`;
      }

      if (Math.abs(windowHeight - clickCoordsY) < menuHeight) {
        const top = parseInt(windowHeight - menuHeight, 10);
        menu.style.top = `${top}px`;
      } else {
        const top = parseInt(clickCoordsY, 10);
        menu.style.top = `${top}px`;
      }
    }
  }

  function contextListener() {
    document.addEventListener('contextmenu', function (e) {
      taskItemInContext = clickInsideElement(e, 'folder');
      let menu = $(taskItemInContext).find('>span.actions')[0];
      if (taskItemInContext && typeof menu !== 'undefined') {
        e.preventDefault();
        toggleMenuOff();
        toggleMenuOn(taskItemInContext, menu);
        positionMenu(e, taskItemInContext, menu);
      } else if (taskItemInContext) {
        e.preventDefault();
      } else {
        taskItemInContext = clickInsideIdElement(e, 'board');
        if (taskItemInContext) {
          taskItemInContext = $('#media-folders')[0];
          menu = $(taskItemInContext).find('>span.actions')[0];
          if (typeof menu !== 'undefined') {
            e.preventDefault();
            toggleMenuOff();
            toggleMenuOn(taskItemInContext, menu);
            positionMenu(e, taskItemInContext, menu);
          } else {
            taskItemInContext = null;
            toggleMenuOff();
          }
        } else {
          taskItemInContext = null;
          toggleMenuOff();
        }
      }
    });
  }

  function clickListener() {
    $('.context-menu .close').on('click', function () {
      taskItemInContext = null;
      toggleMenuOff();
    });
    $('body').on('click', function (e) {
      const button = e.which || e.button;
      if (button === 1) {
        taskItemInContext = null;
        toggleMenuOff();
      }
    });
  }

  function resizeListener() {
    window.onresize = function (e) {
      taskItemInContext = null;
      toggleMenuOff();
    };
  }

  function initMenuFunction() {
    contextListener();
    clickListener();
    resizeListener();
  }

  Drupal.behaviors.media_folders_context_menus = {
    attach(context) {
      toggleMenuOff();
      $(once('media-folders-context-menu', '#media-folders', context)).each(
        function () {
          initMenuFunction();
        },
      );
    },
  };
})(jQuery, Drupal);
