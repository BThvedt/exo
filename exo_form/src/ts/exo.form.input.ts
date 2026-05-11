(function ($, Drupal) {

  class ExoFormInput {
    protected $element:JQuery;
    protected $field:JQuery;
    protected field:HTMLInputElement;
    protected $error:JQuery;

    constructor($element:JQuery) {
      this.$element = $element;
      this.$error = this.$element.find('.field-error');
      this.$field = this.$element.find('.exo-form-input-item-js');
      this.field = this.$field[0] as HTMLInputElement;
      this.$field.each(() => {
        const $prefix = this.$element.find('.field-prefix');
        const $suffix = this.$element.find('.field-suffix');
        const $suffixDescription = $suffix.find('.description');
        this.$field.after('<div class="exo-form-input-line" />');
        if ($prefix.length) {
          $prefix.on('click.exo.form.input', e => {
            this.$field.trigger('focus');
          });
        }
        if ($suffix.length) {
          $suffix.on('click.exo.form.input', e => {
            this.$field.trigger('focus');
          });
        }
        if ($suffixDescription.length) {
          $suffixDescription.appendTo(this.$element);
        }
      });

      this.validate();
      this.bind();
      // Menu title support.
      if (this.$element.hasClass('menu-title')) {
        this.$element.addClass('value');
      }
      if (this.hasValue() || this.isAutofocus() || this.hasPlaceholder() || this.hasBadInput()) {
        this.$element.addClass('active');
      }
      if (this.hasValue()) {
        this.$element.addClass('value');
      }
      setTimeout(() => {
        this.$element.addClass('ready').removeClass('exo-no-animations');
      });
    }

    public destory() {
      this.unbind();
      this.$element.removeData();
    }

    protected bind() {
      this.$field.on('change.exo.form.input', () => {
        this.onChange.call(this);
      });
      this.$field.on('keyup.exo.form.input', () => {
        this.onChange.call(this);
      });
      this.$field.on('input.exo.form.input', () => {
        this.onInput.call(this);
      });
      this.$field.on('focus.exo.form.input', () => {
        this.onFocus.call(this);
      });
      this.$field.on('blur.exo.form.input', () => {
        this.onBlur.call(this);
      });
    }

    protected unbind() {
      this.$field.off('.exo.form.input');
    }

    public onChange(e:JQueryEventObject) {
      if (this.hasValue() || this.hasPlaceholder()) {
        this.$element.addClass('active');
      }
      this.validate();
    }

    public onInput(e:JQueryEventObject) {
      if (this.hasValue()) {
        this.$element.addClass('value');
      }
      else {
        this.$element.removeClass('value');
      }
    }

    public onFocus(e:JQueryEventObject) {
      if (!this.isReadonly()) {
        this.$element.addClass('active focused');
      }
    }

    public onBlur(e:JQueryEventObject) {
      var classes = 'focused';
      if ((!this.hasValue() || (!this.hasValue() && this.isValid())) && !this.hasPlaceholder()) {
        classes += ' active';
      }
      this.$element.removeClass(classes);
      this.validate();
    }

    public validate() {
      this.$element.removeClass('valid invalid').removeAttr('data-error');
      if (this.hasValue()) {
        if (this.isValid()) {
          this.$element.addClass('valid');
        }
        else {
          this.$element.addClass('invalid').attr('data-error', this.field.validationMessage);
        }
      }
      else if (this.hasError()) {
        this.$element.addClass('invalid');
      }
    }

    public hasValue() {
      var value = this.$field.val();
      return value !== '' && value !== '- Any -';
    }

    public hasPlaceholder() {
      var placeholder = this.$field.attr('placeholder');
      return typeof placeholder !== 'undefined' && placeholder.length > 0;
    }

    public hasError() {
      return this.$field.hasClass('error');
    }

    public hasBadInput() {
      return this.field.validity.badInput === true;
    }

    public isValid() {
      return this.field.validity.valid === true && !this.hasError();
    }

    public isAutofocus() {
      var autofocus = this.$field.attr('autofocus');
      return typeof autofocus !== 'undefined';
    }

    public isReadonly() {
      var readonly = this.$field.attr('readonly');
      return typeof readonly !== 'undefined';
    }
  }

  /**
   * Input build behavior.
   */
  Drupal.behaviors.exoFormInput = {
    attach: function(context) {
      // core/once replaces the jQuery.once removed in Drupal 11.
      once('exo.form.input', '.form-item.exo-form-input-js', context).forEach((element:HTMLElement) => {
        new ExoFormInput($(element));
      });
      Drupal.Exo.event('ready').on('exo.form.input', e => {
        $('.form-item.exo-form-input-js.exo-form-element-float, .form-item.exo-form-input-js.exo-form-element-float-inside').each((index, element) => {
          const $element = $(element);
          const $prefix = $element.find('.field-prefix');
          if ($prefix.length) {
            $element.find('> .field-label').css('left', $prefix.outerWidth());
          }
        });
      });
    }
  }

})(jQuery, Drupal);
