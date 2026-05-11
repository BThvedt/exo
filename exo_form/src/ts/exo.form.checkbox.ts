(function ($, Drupal) {

  class ExoFormCheckbox {
    protected $element:JQuery;
    protected $field:JQuery;
    protected $label:JQuery;
    protected field:HTMLInputElement;

    constructor($element:JQuery) {
      this.$element = $element;
      this.$field = this.$element.find('input:first');
      this.$label = this.$element.find('label');
      if (!this.$label.length) {
        const id = this.$field.attr('id') || Drupal.Exo.guid();
        this.$field.attr('id', id);
        this.$field.parent().append('<label for="' + id + '" class="option"><div class="exo-ripple"></div></label>');
      }
      else if (!this.$label.hasClass('option')) {
        this.$label.addClass('option');
      }
      this.field = this.$field[0] as HTMLInputElement;
      if (this.$field.prop('checked')) {
        this.$element.addClass('active');
      }
      if (this.hasError()) {
        this.$element.addClass('invalid');
      }
      this.bind();
      setTimeout(() => {
        this.$element.addClass('ready');
        // Support dummy checkboxes.
        // @see user.permissions.js
        const $dummy = this.$element.find('.dummy-checkbox');
        if ($dummy.length) {
          this.$element.find('label.option').addClass('js-real-checkbox').css('display', $dummy.css('display') === 'none' ? '' : 'none');
        }
      });
    }

    public destory() {
      this.unbind();
      this.$element.removeData();
    }

    protected bind() {
      this.$field.on('change.exo.form.checkbox', e => {
        this.onChange.call(this);
        this.validate();
      }).on('focus.exo.form.checkbox', e => {
        this.$element.addClass('focused');
        this.validate();
      }).on('blur.exo.form.checkbox', e => {
        this.$element.removeClass('focused');
      });
    }

    protected unbind() {
      this.$field.off('.exo.form.checkbox');
    }

    public onChange(e:JQueryEventObject) {
      if (this.$field.prop('checked')) {
        this.$element.addClass('active');
      }
      else {
        this.$element.removeClass('active');
      }
    }

    protected validate() {
      this.$element.removeClass('valid invalid').removeAttr('data-error');
      if (!this.isValid()) {
        this.$element.addClass('invalid').attr('data-error', this.field.validationMessage);
      }
    }

    protected isValid() {
      return this.field.validity.valid === true;
    }

    public hasError() {
      return this.$field.hasClass('error');
    }
  }

  /**
   * Toolbar build behavior.
   */
  Drupal.behaviors.exoFormCheckbox = {
    attach: function(context) {
      // core/once replaces the jQuery.once removed in Drupal 11.
      once('exo.form.checkbox', '.exo-form-checkbox-js', context).forEach((element:HTMLElement) => {
        new ExoFormCheckbox($(element));
      });
    },

    wrap: function($element:JQuery) {
      const $wrap = $('<div class="exo-form-checkbox exo-form-checkbox-js"><div class="field-input"></div></div>');
      const $label = $element.parent().find('> label:first');
      if ($label.length) {
        const $html = $label.html();
        $element.wrap($label.html(''));
        $element.after($html);
        $label.remove();
        $element = $element.parent();
      }
      $element.wrap($wrap);
      Drupal.behaviors.exoFormCheckbox.attach($element.closest('.exo-form-checkbox-js').parent());
    }
  }

})(jQuery, Drupal);
