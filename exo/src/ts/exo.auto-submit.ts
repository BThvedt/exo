/**
 * @file auto_submit.js
 *
 * Provides a "form auto-submit" feature for the Better Exposed Filters module.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * To make a form auto submit, all you have to do is 3 things:
   *
   * Use the "exo/exo.auto_submit" js library.
   *
   * On gadgets you want to auto-submit when changed, add the
   * data-exo-auto-submit attribute. With FAPI, add:
   * @code
   *  '#attributes' => array('data-exo-auto-submit' => ''),
   * @endcode
   *
   * If you want to have auto-submit for every form element, add the
   * data-exo-auto-submit-full-form to the form. With FAPI, add:
   * @code
   *   '#attributes' => array('data-exo-auto-submit-full-form' => ''),
   * @endcode
   *
   * If you want to exclude a field from the exo-auto-submit-full-form auto
   * submission, add an attribute of data-exo-auto-submit-exclude to the form
   * element. With FAPI, add:
   * @code
   *   '#attributes' => array('data-exo-auto-submit-exclude' => ''),
   * @endcode
   *
   * Finally, you have to identify which button you want clicked for autosubmit.
   * The behavior of this button will be honored if it's ajaxy or not:
   * @code
   *  '#attributes' => array('data-exo-auto-submit-click' => ''),
   * @endcode
   *
   * Currently only 'select', 'radio', 'checkbox' and 'textfield' types are
   * supported. We probably could use additional support for HTML5 input types.
   */
  Drupal.behaviors.exoAutoSubmit = {
    focused: null,

    submit: function (e:JQueryEventObject) {
      this.focused = e.target.getAttribute('data-drupal-selector');
      const $target = $(e.target);
      if (!$target.closest('.exo-auto-submit-disable').length) {
        $target.closest('form').find('[data-exo-auto-submit-click]').first().click();
      }
    },

    attach: function (context) {

      const discardKeyCode = [
        16, // shift
        17, // ctrl
        18, // alt
        20, // caps lock
        33, // page up
        34, // page down
        35, // end
        36, // home
        37, // left arrow
        38, // up arrow
        39, // right arrow
        40, // down arrow
        9, // tab
        13, // enter
        27  // esc
      ];

      if (this.focused) {
        setTimeout(() => {
          const $focused = $('[data-drupal-selector="' + this.focused + '"]', context);
          if ($focused.length) {
            $focused.focus();
            if ($focused.is('input:text')) {
              const tmpStr = $focused.val();
              $focused.val('');
              $focused.val(tmpStr);
            }
            this.focused = null;
          }
        });
      }

      // The change event bubbles so we only need to bind on the outer form.
      // core/once requires plain DOM elements as targets, so build the
      // candidate set first via jQuery's filter chain, then hand the
      // resulting HTMLElement array to once().
      const candidates = $('form[data-exo-auto-submit-full-form]', context)
        .add('[data-exo-auto-submit]', context)
        .filter('form, select, input:not(:text, :submit)')
        .get();
      once('exo.auto-submit', candidates).forEach((element:HTMLElement) => {
        let timeoutID: ReturnType<typeof setTimeout> = null;
        $(element).on('change', e => {
          // don't trigger on text change for full-form
          if ($(e.target).is(':not(:text, :submit, [data-exo-auto-submit-exclude])')) {
            timeoutID = setTimeout(() => {
              this.submit(e);
            }, 10);
          }
        });
      });
    }
  };

}(jQuery, Drupal));
