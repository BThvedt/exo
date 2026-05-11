(function ($, Drupal, drupalSettings) {

  class ExoFormTime {
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
      this.$input = this.$element.find('input[type="time"]');
      this.$input.data('value', this.$input.val());
      this.$input.pickatime(this.settings);
      // const method = 'build' + this.settings.mode.charAt(0).toUpperCase() + this.settings.mode.slice(1);
      // if (typeof this[method] === 'function') {
      //   this[method]();
      // }
    }

    // public buildButton() {
    //   const $button = this.$element.find('.exo-form-time-button');
    //   this.$input.attr('type', 'time');
    //   $button.on('click', e => {
    //     e.preventDefault();
    //     e.stopPropagation();
    //     this.$input.pickatime('picker').open();
    //   });
    // }
  }

  /**
   * Toolbar build behavior.
   */
  Drupal.behaviors.exoFormTime = {
    attach: function(context) {
      if (drupalSettings.exoForm && drupalSettings.exoForm.time && drupalSettings.exoForm.time.items) {
        for (var id in drupalSettings.exoForm.time.items) {
          if (drupalSettings.exoForm.time.items[id]) {
            // core/once replaces the jQuery.once removed in Drupal 11.
            once('exo.form.time', '#' + id, context).forEach((element:HTMLElement) => {
              new ExoFormTime($(element), drupalSettings.exoForm.time.items[id]);
            });
          }
        }
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
