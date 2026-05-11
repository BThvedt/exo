(function ($, Drupal, drupalSettings) {

  class ExoFormDate {
    protected defaults:ExoSettingsGroupInterface = {
      mode: 'button',
      container: '#exo-content',
      format: 'yyyy-mm-dd'
    };
    protected $element:JQuery;
    protected $input:JQuery;
    protected settings:ExoSettingsGroupInterface;

    constructor($element:JQuery, settings:ExoSettingsGroupInterface) {
      this.$element = $element;
      this.settings = _.extend({}, this.defaults, settings);
      this.$input = this.$element.find('input[type="date"]');
      this.$input.data('value', this.$input.val());
      this.$input.pickadate(this.settings);

      const method = 'build' + this.settings.mode.charAt(0).toUpperCase() + this.settings.mode.slice(1);
      if (typeof this[method] === 'function') {
        this[method]();
      }
    }

    public buildButton() {
      const $button = this.$element.find('.exo-form-date-button');
      this.$input.attr('type', 'date');
      $button.on('click', e => {
        e.preventDefault();
        e.stopPropagation();
        this.$input.pickadate('picker').open();
      });
    }
  }

  /**
   * Toolbar build behavior.
   */
  Drupal.behaviors.exoFormDate = {
    attach: function(context) {
      if (drupalSettings.exoForm && drupalSettings.exoForm.date && drupalSettings.exoForm.date.items) {
        for (var id in drupalSettings.exoForm.date.items) {
          if (drupalSettings.exoForm.date.items[id]) {
            // core/once replaces the jQuery.once removed in Drupal 11.
            once('exo.form.date', '#' + id, context).forEach((element:HTMLElement) => {
              new ExoFormDate($(element), drupalSettings.exoForm.date.items[id]);
            });
          }
        }
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
