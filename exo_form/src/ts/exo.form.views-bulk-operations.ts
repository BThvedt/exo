/**
 * @file
 * Select-All Button functionality.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.exoFormViewsBulkOperations = {
    attach: function (context, settings) {
      // core/once replaces the jQuery.once removed in Drupal 11.
      once('exo.form.vbo-init', '.vbo-view-form').forEach((element:HTMLElement) => {
        const $vboForm = $(element);
        const $primarySelectAll = $('.vbo-select-all', $vboForm);
        const $tableSelectAll = $('th.select-all > input[type="checkbox"]', $vboForm);
        if ($primarySelectAll.length) {
          Drupal.behaviors.exoFormCheckbox.wrap($primarySelectAll);
          $primarySelectAll.on('change', function (event) {
            $vboForm.find('.views-field-views-bulk-operations-bulk-form input[type="checkbox"]').trigger('change');
          });
        }
        if ($tableSelectAll.length) {
          Drupal.behaviors.exoFormCheckbox.wrap($tableSelectAll);
        }
      });
    }
  };

})(jQuery, Drupal);
