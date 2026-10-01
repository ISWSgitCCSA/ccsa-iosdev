/**
 * @file
 * IOS language and light/dark preference controls.
 *
 * Drupal determines the active language.
 *
 * Canonical selected tone:
 *   cool-light-bg
 *
 * The selected language link and selected colour-mode button receive
 * the same tonal utility class.
 */

(() => {
  "use strict";

  const storageKey = "ios-colour-mode";
  const root = document.documentElement;
  const selectedToneClass = "cool-light-bg";

  /**
   * Get the visitor's saved colour mode.
   *
   * @returns {string}
   *   "light" or "dark".
   */
  const getSavedMode = () => {
    try {
      return window.localStorage.getItem(storageKey) === "dark"
        ? "dark"
        : "light";
    }
    catch (error) {
      return "light";
    }
  };

  /**
   * Save the visitor's colour-mode choice.
   *
   * @param {string} mode
   *   "light" or "dark".
   */
  const saveMode = (mode) => {
    try {
      window.localStorage.setItem(storageKey, mode);
    }
    catch (error) {
      // The control still works when storage is unavailable.
    }
  };

  /**
   * Apply the selected tonal class to Drupal's active language link.
   */
  const updateLanguageControl = () => {
    document
      .querySelectorAll(".ios-preferences__item")
      .forEach((item) => {
        const link = item.querySelector(
          ".ios-preferences__option--language"
        );

        if (!link) {
          return;
        }

        const isActive =
          item.classList.contains("is-active") ||
          link.classList.contains("is-active") ||
          item.getAttribute("aria-current") === "page" ||
          link.getAttribute("aria-current") === "page";

        /*
         * Older iterations placed the tonal class on the <li>.
         * Remove it there and keep the selected tone on the actual
         * interactive option so language and colour-mode controls
         * use the same structure.
         */
        item.classList.remove(
          "cool-light-bg",
          "warm-light-bg"
        );

        link.classList.remove(
          "warm-light-bg"
        );

        link.classList.toggle(
          selectedToneClass,
          isActive
        );
      });
  };

  /**
   * Update colour-mode selected state.
   *
   * @param {string} mode
   *   "light" or "dark".
   */
  const updateThemeControls = (mode) => {
    document
      .querySelectorAll("[data-ios-theme-mode]")
      .forEach((control) => {
        const isSelected =
          control.dataset.iosThemeMode === mode;

        control.setAttribute(
          "aria-pressed",
          String(isSelected)
        );

        control.classList.remove(
          "warm-light-bg"
        );

        control.classList.toggle(
          selectedToneClass,
          isSelected
        );
      });
  };

  /**
   * Apply the selected colour mode.
   *
   * @param {string} mode
   *   "light" or "dark".
   * @param {boolean} persist
   *   Whether to save the visitor's choice.
   */
  const applyMode = (mode, persist = false) => {
    const selectedMode =
      mode === "dark"
        ? "dark"
        : "light";

    if (selectedMode === "dark") {
      root.setAttribute(
        "data-ios-theme",
        "dark"
      );
    }
    else {
      root.removeAttribute(
        "data-ios-theme"
      );
    }

    updateThemeControls(selectedMode);

    if (persist) {
      saveMode(selectedMode);
    }
  };

  /*
   * Apply the saved colour preference as early as possible.
   */
  applyMode(getSavedMode());

  /**
   * Connect controls once their markup exists.
   */
  const initialiseControls = () => {
    updateLanguageControl();

    document
      .querySelectorAll("[data-ios-theme-mode]")
      .forEach((control) => {
        control.addEventListener(
          "click",
          () => {
            applyMode(
              control.dataset.iosThemeMode,
              true
            );
          }
        );
      });

    updateThemeControls(getSavedMode());
  };

  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      initialiseControls,
      { once: true }
    );
  }
  else {
    initialiseControls();
  }
})();
