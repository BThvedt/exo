(function ($, Drupal) {

  class ExoFormFile {
    protected $element:JQuery;
    protected $field:JQuery;

    constructor($element:JQuery) {
      this.$element = $element;
      this.$field = this.$element.find('input[type="file"]');
      this.$field.after('<div class="exo-form-input-line" />');
      this.bind();
    }

    protected bind() {
      this.$field.on('change.exo.form.file', () => {
        this.onChange.call(this);
      });
    }

    public onChange(e: JQueryEventObject) {
      if (this.$field.val() != '') {
        var $fileName = this.$field.val().toString().replace(/.*(\/|\\)/, '');
        this.$field.closest('.exo-form-file-input').attr('data-text', $fileName);
      }
    }

  }

  /**
   * Toolbar build behavior.
   */
  Drupal.behaviors.exoFormFile = {
    attach: function (context) {
      // core/once replaces the jQuery.once removed in Drupal 11.
      once('exo.form.file', '.exo-form-file-js', context).forEach((element:HTMLElement) => {
        new ExoFormFile($(element));
      });
    }
  }

})(jQuery, Drupal);
