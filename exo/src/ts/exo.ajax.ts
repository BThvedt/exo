/**
 * @file
 * Bind exo links.
 */

(function ($, Drupal) {
  /**
   * Attaches the Ajax behavior to each Ajax form element.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Initialize all {@link Drupal.Ajax} objects declared in
   *   `drupalSettings.ajax` or initialize {@link Drupal.Ajax} objects from
   *   DOM elements having the `use-ajax-submit` or `use-ajax` css class.
   * @prop {Drupal~behaviorDetach} detach
   *   During `unload` remove all {@link Drupal.Ajax} objects related to
   *   the removed content.
   */
  Drupal.behaviors.exoAjax = {
    attach: function (context, settings) {

      // Bind Ajax behaviors to all items showing the class.
      // core/once returns Array<HTMLElement>; re-wrap each element in $()
      // to preserve the rest of the jQuery API.
      once('ajax', '.exo-ajax', context).forEach((element:HTMLElement) => {
        const $element = $(element);
        const element_settings = {} as any;
        // Clicked links look better with the throbber than the progress bar.
        element_settings.progress = {type: 'fullscreen'};

        // For anchor tags, these will go to the target of the anchor rather
        // than the usual location.
        const href = $element.attr('href');
        if (href) {
          element_settings.url = href;
          element_settings.event = 'click';
        }
        element_settings.dialogType = $element.data('dialog-type');
        element_settings.dialog = $element.data('dialog-options');
        element_settings.base = $element.attr('id');
        element_settings.element = element;
        Drupal.ajax(element_settings);
      });

    }
  };

}(jQuery, Drupal));
