(function ($, Drupal, displace) {

  /**
   * Autogrow.
   */
  Drupal.behaviors.exoFormAutogrow = {
    attach: function(context) {
      // core/once replaces the jQuery.once removed in Drupal 11. Wrap
      // the resulting element array back in $() so the existing jQuery
      // chain (autosize, .each, etc.) continues to work unchanged.
      const $elements = $(once('exo.form.autogrow', 'textarea[data-autogrow]', context));
      if ($elements.length) {
        Drupal.Exo.event('ready').on('exo.form.autogrow', function () {
          $elements.each(function () {
            const $element = $(this);
            const maxHeight = $element.data('autogrow-max');
            if (maxHeight) {
              $element.css('max-height', maxHeight);
            }
          });
          autosize($elements);
        });
      }
    }
  }

})(jQuery, Drupal, Drupal.displace);
