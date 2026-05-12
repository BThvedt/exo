/**
 * @file
 * Select as links javascript.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.exoButton = {
    attach: function (context) {
      // core/once returns plain Array<HTMLElement>; re-wrap per element so
      // the existing jQuery chain still works on each trigger.
      once('exo-button', '.exo-button-trigger', context).forEach((element:HTMLElement) => {
        $(element).on('click', function (e) {
          e.preventDefault();
          $(this).closest('.exo-button').find('.js-form-submit').trigger('mousedown').trigger('mouseup').trigger('click');
        });
      });
    }
  };

}(jQuery, Drupal));
