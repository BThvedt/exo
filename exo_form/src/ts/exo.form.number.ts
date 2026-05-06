(function ($, Drupal) {

  class ExoFormNumber {
    protected $element:JQuery;
    protected $input:JQuery;

    constructor($element:JQuery) {
      this.$element = $element;
      this.$input = this.$element.find('input[type="number"]');
      const amount = this.$input.prop('step');

      // The field-prefix / field-suffix spans hold the - and + icon
      // controls. By default they're plain <span>s with click handlers,
      // so they're invisible to assistive tech and unreachable via
      // keyboard. Apply the WAI-ARIA Button pattern so they're focusable
      // and announce as buttons, and add keyboard activation for
      // Enter and Space.
      const $decrease = this.$element.find('.field-prefix');
      const $increase = this.$element.find('.field-suffix');
      const inputId = this.$input.attr('id') || '';
      $decrease
        .attr('role', 'button')
        .attr('tabindex', '0')
        .attr('aria-label', Drupal.t('Remove one'));
      $increase
        .attr('role', 'button')
        .attr('tabindex', '0')
        .attr('aria-label', Drupal.t('Add one'));
      if (inputId) {
        $decrease.attr('aria-controls', inputId);
        $increase.attr('aria-controls', inputId);
      }

      $decrease.on('click.exo.form.number', e => {
        this.adjust('decrease', amount);
      });
      $increase.on('click.exo.form.number', e => {
        this.adjust('increase', amount);
      });

      // Keyboard activation per the WAI-ARIA Button pattern.
      const onActivateKey = (op:string) => (e:JQuery.KeyDownEvent) => {
        if (e.which === 13 || e.which === 32) {
          // Space normally scrolls the page; Enter would submit a form.
          e.preventDefault();
          this.adjust(op, amount);
        }
      };
      $decrease.on('keydown.exo.form.number', onActivateKey('decrease'));
      $increase.on('keydown.exo.form.number', onActivateKey('increase'));

      this.$input.on('keypress.exo.form.number', function (e) {
        return (e.charCode == 8 || e.charCode == 0 || e.charCode == 13) ? null : (e.charCode >= 48 && e.charCode <= 57) || e.charCode === 46;
      });
    }

    public adjust(op:string, amount?:string) {
      amount = amount || '1';
      const oldValue:string = this.$input.val() as string || '0';
      let newValue = 0;
      switch (op) {
        case 'increase':
          newValue = parseFloat(oldValue) + parseFloat(amount);
          break;
        case 'decrease':
          newValue = parseFloat(oldValue) - parseFloat(amount);
          if (newValue < 0) {
            newValue = 0;
          }
          break;
      }
      // Respect <input> min / max if set.
      const minAttr = this.$input.attr('min');
      const maxAttr = this.$input.attr('max');
      if (minAttr !== undefined && minAttr !== '' && newValue < parseFloat(minAttr)) {
        newValue = parseFloat(minAttr);
      }
      if (maxAttr !== undefined && maxAttr !== '' && newValue > parseFloat(maxAttr)) {
        newValue = parseFloat(maxAttr);
      }
      this.$input.val(newValue).trigger('change');
    }
  }

  /**
   * Toolbar build behavior.
   */
  Drupal.behaviors.exoFormNumber = {
    attach: function(context) {
      $(context).find('.exo-form-number-js').once('exo.form.number').each((index, element) => {
        new ExoFormNumber($(element));
      });
    }
  }

})(jQuery, Drupal);
