(function ($, Drupal) {

  class ExoAlchemistEnhancementTabs {

    protected $wrapper:JQuery;
    protected $triggers:JQuery;
    protected $contents:JQuery;
    protected id:string = '';
    protected idSelector:string = '';
    protected history:boolean = false;
    protected unhash:boolean = false;

    constructor(id:string, $wrapper:JQuery) {
      this.$wrapper = $wrapper;
      this.id = id;
      this.idSelector = 'data-ee--tabs-id="' + this.id + '"';
      this.$triggers = $wrapper.find('.ee--tabs-trigger[' + this.idSelector + ']');
      this.$contents = $wrapper.find('.ee--tabs-content[' + this.idSelector + ']');
      this.history = typeof $wrapper.data('ee--accordion-history') !== 'undefined';
      this.unhash = typeof $wrapper.data('ee--accordion-unhash') !== 'undefined';
      this.$contents.hide();
      let $show = this.$triggers.first();
      Drupal.Exo.$window.on('popstate.exo.alchemist.enhancement.tabs.' + this.id, e => {
        if (this.unhash) {
          const hash = window.location.hash.replace('#', '');
          if (hash) {
            const $item = this.findTriggerByHash(hash);
            if ($item.length) {
              this.show($item.first(), false);
              return;
            }
          }
          this.show(this.$triggers.first(), false);
        } else {
          const hashTab = Drupal.ExoAlchemistEnhancement.getHashForKey('ee--tabs');
          if (hashTab && typeof hashTab[this.id] !== 'undefined') {
            const $item = this.$triggers.filter('[data-ee--tab-id="' + hashTab[this.id] + '"]');
            if ($item.length) {
              this.show($item.first(), false);
              return;
            }
          }
          this.show(this.$triggers.first(), false);
        }
      });
      if (this.isLayoutBuilder()) {
        Drupal.ExoAlchemistAdmin.lockNestedFields(this.$triggers);
        Drupal.Exo.$document.on('exoComponentFieldEditActive.exo.alchemist.enhancement.tabs', (e, element) => {
          const $element = $(element);
          const $content = $element.closest('.ee--tabs-content');
          if ($content.length) {
            const id = $content.data('ee--tab-id');
            const $trigger = this.$triggers.filter('[data-ee--tab-id="' + id + '"]');
            this.show($trigger, this.history);
            Drupal.ExoAlchemistAdmin.sizeFieldOverlay($element);
            Drupal.ExoAlchemistAdmin.sizeTarget($element);
          }
        });
      }
      else {
        const hashTab = Drupal.ExoAlchemistEnhancement.getHashForKey('ee--tabs');
        if (hashTab && typeof hashTab[this.id] !== 'undefined') {
          const $item = this.$triggers.filter('[data-ee--tab-id="' + hashTab[this.id] + '"]');
          if ($item.length) {
            $show = $item.first();
          }
        }
      }
      this.show($show, false);
      this.$triggers.on('click.exo.alchemist.enhancement.tabs', e => {
        e.preventDefault();
        this.show($(e.currentTarget), this.history);
      }).on('keydown.exo.alchemist.enhancement.accordion', e => {
        let $goto;
        switch (e.which) {
          case 13: // enter
          case 32: // space
            e.preventDefault();
            e.stopPropagation();
            this.show($(e.currentTarget), this.history);
            break;
          case 40: // down
            e.preventDefault();
            e.stopPropagation();
            $goto = $(e.currentTarget).next();
            if ($goto.length) {
              this.show($goto, this.history);
              $goto.focus();
            }
            break;
          case 38: // up
            e.preventDefault();
            e.stopPropagation();
            $goto = $(e.currentTarget).prev();
            if ($goto.length) {
              this.show($goto, this.history);
              $goto.focus();
            }
            break;
        }
      });
    }

    public show($trigger:JQuery, setHash?:boolean):void {
      const id = $trigger.data('ee--tab-id');
      const isShown = $trigger.hasClass('active');
      setHash = setHash !== false;
      if (isShown) {
        return;
      }
      this.$triggers.removeClass('active');
      $trigger.addClass('active');
      this.$contents.removeClass('active').hide();
      const $content = this.$contents.filter('[data-ee--tab-id="' + id + '"]');
      $content.addClass('active').show();
      Drupal.Exo.$window.trigger('ee--tab.open', {trigger: $trigger, content: $content});
      // Notify exo about a change in positions.
      Drupal.Exo.checkElementPosition();
      if (this.isLayoutBuilder()) {
        Drupal.ExoAlchemistAdmin.lockNestedFields(this.$triggers);
        Drupal.ExoAlchemistAdmin.unlockNestedFields($trigger);
      }
      else if (setHash) {
        if (this.unhash) {
          // Use the trigger text for the URL hash, removing leading/trailing hyphens
          const hashText = $trigger.text().toLowerCase().trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
          window.location.hash = hashText;
        }
        else if (this.history) {
          Drupal.ExoAlchemistEnhancement.setHashForKey('ee--tabs', id, this.id);
        }
      }
    }

    protected findTriggerByHash(hash:string):JQuery {
      return this.$triggers.filter((i, el) => {
        const text = $(el).text().toLowerCase().trim()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');
        return text === hash;
      });
    }

    protected buildTabHash(id:string) {
      if (this.unhash) {
        const $trigger = this.$triggers.filter('[data-ee--tab-id="' + id + '"]');
        return $trigger.text().toLowerCase().trim()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');
      }
      return 'ee--tab-' + this.id + '--' + id;
    }

    protected extractTabIdFromHash(hash:string) {
      if (this.unhash) {
        const $matchingTrigger = this.findTriggerByHash(hash);
        return $matchingTrigger.length ? $matchingTrigger.data('ee--tab-id') : null;
      }
      const parts = hash.replace('ee--tab-', '').split('--');
      return parts[1];
    }

    public unload() {
      Drupal.Exo.$document.off('exoComponentFieldEditActive.exo.alchemist.enhancement.tabs.' + this.id);
      Drupal.Exo.$window.off('popstate.exo.alchemist.enhancement.tabs.' + this.id);
    }

    protected isLayoutBuilder() {
      return Drupal.ExoAlchemistAdmin && Drupal.ExoAlchemistAdmin.isLayoutBuilder();
    }

  }

  /**
   * eXo Alchemist enhancement behavior.
   */
  Drupal.behaviors.exoAlchemistEnhancementTabs = {
    count: 0,
    instances: {},
    attach: function(context) {
      var self = this;
      $('.ee--tabs-wrapper', context).once('exo.alchemist.enhancement').each(function () {
        const $wrapper = $(this);
        const id = $wrapper.data('ee--tabs-id');
        $wrapper.data('ee--tabs-count', self.count);
        self.instances[id + self.count] = new ExoAlchemistEnhancementTabs(id, $wrapper);
        self.count++;
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger === 'unload') {
        var self = this;
        $('.ee--tabs-wrapper', context).each(function () {
          const $wrapper = $(this);
          const id = $wrapper.data('ee--tabs-id') + $wrapper.data('ee--tabs-count');
          if (typeof self.instances[id] !== 'undefined') {
            self.instances[id].unload();
            delete self.instances[id];
          }
        });
      }
    }
  }

})(jQuery, Drupal);
