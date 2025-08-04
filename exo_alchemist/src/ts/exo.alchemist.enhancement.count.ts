(function ($, Drupal, countUp) {

  class ExoAlchemistEnhancementCount {

    protected $wrapper:JQuery;
    protected id:string = '';
    protected count:any;

    constructor(id:string, $wrapper:JQuery) {
      this.$wrapper = $wrapper;
      this.id = id;
      const $component = this.$wrapper.closest('.exo-component');
      const value = this.$wrapper.text().trim();
      const number = parseFloat(value.match(/[\d\.]+/i)[0] || ''); //eslint-disable-line
      const prefix = value.match(/[^\d\.]*/i)[0] || ''; //eslint-disable-line
      const suffix = value.match(/[\d\.]+(.*)/i)[1] || ''; //eslint-disable-line
      const decimals = (value.match(/\./g) || []).length; //eslint-disable-line
      const useGrouping = this.$wrapper.data('no-grouping') !== true;
      this.$wrapper.css({
        width: this.$wrapper.width() + 'px',
        overflow: 'visible',
      });
      this.$wrapper.text(prefix + '0' + suffix);

      const doCount = (e) => {
        var count = new countUp.CountUp(this.$wrapper[0], number, {
          decimalPlaces: decimals,
          prefix: prefix,
          suffix: suffix,
          duration: 3,
          useGrouping: useGrouping
        });
        count.start();
      };

      $component.one(Drupal.Exo.transitionEvent + '.ash.count', doCount);
    }

  }

  /**
   * eXo Alchemist enhancement behavior.
   */
  Drupal.behaviors.exoAlchemistEnhancementCount = {
    count: 0,
    instances: {},
    attach: function(context) {
      const self = this;
      $('.ee--count-wrapper', context).once('exo.alchemist.enhancement').each(function () {
        const $wrapper = $(this);
        const id = $wrapper.data('ee--count-id');
        $wrapper.data('ee--count-count', self.count);
        self.instances[id + self.count] = new ExoAlchemistEnhancementCount(id, $wrapper);
        self.count++;
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger === 'unload') {
        const self = this;
        $('.ee--count-wrapper', context).each(function () {
          const $wrapper = $(this);
          const id = $wrapper.data('ee--count-id') + $wrapper.data('ee--count-count');
          if (typeof self.instances[id] !== 'undefined') {
            self.instances[id].unload();
            delete self.instances[id];
          }
        });
      }
    }
  }

})(jQuery, Drupal, window.countUp);
