(function ($) {

  class ExoMenuStyleMegaVertical extends ExoMenuStyleBase {
    protected defaults:ExoSettingsGroupInterface = {
      // delay between each menu item sliding animation
      itemDelayInterval: 60,
      width: '50%',
      transitionIn: 'fadeIn', // comingIn, bounceInDown, bounceInUp, fadeInDown, fadeInUp, fadeInLeft, fadeInRight, flipInX
      transitionOut: 'fadeOut', // comingOut, bounceOutDown, bounceOutUp, fadeOutDown, fadeOutUp, , fadeOutLeft, fadeOutRight, flipOutX
      // icon used to signify menu items that will open a submenu
      itemIcon: '',
    }
    protected open:boolean = false;
    protected modal:ExoModal;
    protected $back:JQuery;

    public build() {
      super.build();

      // eXo Modal support.
      if (Drupal.ExoModal) {
        // Modal ID and Menu ID are the same if generated via ExoMenuModalBlock.
        Drupal.ExoModal.getById(this.exoMenu.getId()).then((modal:ExoModal) => {
          this.modal = modal;
        });
      }

      this.$element.find('.level-0 > ul > li.expanded > .exo-menu-link').on('click.exo.menu.style.mega', e => {
        const $target = $(e.currentTarget);
        e.preventDefault();
        this.toggle($target.parent());
      }).each((index, element) => {
        if (this.get('itemIcon')) {
          $(element).find('> span').append(this.get('itemIcon'));
        }
        element.style.webkitAnimationDelay = element.style.animationDelay = index * this.get('itemDelayInterval') + 'ms';
      });

      this.$element.find('.level-1').each((index, element) => {
        $(element).find('.exo-menu-link').each((index, element) => {
          element.style.webkitAnimationDelay = element.style.animationDelay = index * this.get('itemDelayInterval') + 'ms';
        })
      });

      if (this.get('transitionIn') !== '' && Drupal.Exo.animationEvent !== undefined) {
        this.$element.find('.level-0').addClass('exo-animate exo-animate-in exo-animate-' + this.get('transitionIn'));
      }

      this.refresh();
      this.resize();

      Drupal.Exo.$document.on('drupalViewportOffsetChange.exo.menu.mega.vertical', (event) => {
        this.resize();
      });
    }

    public refresh() {
      this.hideActive(false);
      const $trail = this.$element.find('.level-0 > ul > .active-trail');
      if ($trail.length) {
        this.hideActive(false).then(status => this.show($trail, true));
      }
      this.resize();
    }

    public resize() {
      let width = Drupal.Exo.getMeasurementValue(this.get('width'));
      let unit = Drupal.Exo.getMeasurementUnit(this.get('width')) || 'px';
      let level0Width = width;
      let level1Width = 0;
      this.$element.removeClass('exo-menu-can-shift');
      this.$element.removeClass('exo-menu-shift');
      switch (unit) {
        case 'px':
          if (this.open === true) {
            let width2 = Drupal.Exo.getMeasurementValue(this.get('width2')) || width;
            var $active = this.$element.find('.expand').first();
            if ((Drupal.Exo.breakpoint.name === 'large' || Drupal.Exo.breakpoint.name === 'xlarge') && $active.length && $active.attr('data-exo-menu-width')) {
              let widthCustom = $active.attr('data-exo-menu-width');
              level1Width = Drupal.Exo.getMeasurementValue(widthCustom);
            }
            else {
              level1Width = width2;
            }
          }
          else {
            level0Width += width;
          }
          if ((level0Width + level1Width) > Drupal.Exo.$window.width()) {
            // Switch to pane toggle mode.
            this.addBack();
            this.shift();
            width = level0Width = level1Width = 100;
            unit = '%';
          }
          break;
        case '%':
          if (this.open === true) {
            level1Width = 100 - level0Width;
          }
          else {
            level0Width = 100;
          }
          break;
      }
      // Modal follows size.
      if (this.modal) {
        if (!this.modal.hasOpenPanel()) {
          if (this.open === true) {
            this.modal.setWidth(level0Width + level1Width + unit);
          }
          else {
            this.modal.setWidth(width + unit);
          }
        }
      }
      this.$element.find('.level-0 > ul').css({width: level0Width + unit});
      this.$element.find('.level-1').css({width: level1Width + unit});
    }

    protected toggle($item:JQuery, animate?:boolean) {
      animate = animate !== false;
      if ($item.hasClass('expand')) {
        this.hide($item, animate);
      }
      else {
        this.hideActive(animate).then(status => this.show($item, animate));
      }
    }

    protected hideActive(animate?:boolean):Promise<boolean> {
      return new Promise((resolve, reject) => {
        const $active = this.$element.find('.level-0 > ul > .expand');
        if ($active.length) {
          this.hide($active, animate).then(status => {
            resolve(true);
          });
        }
        else {
          resolve(true);
        }
      });
    }

    protected addBack() {
      if (!this.$back) {
        this.$back = $('<a href="#" class="exo-menu-back">Back</a>').on('click.exo.menu.mega.vertical', e => {
          e.preventDefault();
          this.hideActive();
        }).prependTo(this.$element.find('.level-1'));
      }
    }

    protected shift() {
      this.$element.addClass('exo-menu-can-shift');
      if (this.open === true) {
        this.$element.addClass('exo-menu-shift');
      }
    }

    protected show($item:JQuery, animate?:boolean):Promise<boolean> {
      return new Promise((resolve, reject) => {
        const $submenu = $item.find('> .exo-menu-level');
        animate = animate !== false;
        if ($submenu.length) {
          this.open = true;
          $item.addClass('expand');
          this.resize();
          if (animate && this.get('transitionIn') !== '' && Drupal.Exo.animationEvent !== undefined) {
            $submenu.off(Drupal.Exo.animationEvent + '.exo.menu.hide');
            $item.removeClass('exo-animate exo-animate-out');
            $item.addClass('exo-animate exo-animate-in');
            $submenu.removeClass('exo-animate-' + this.get('transitionOut'));
            $submenu.addClass('exo-animate-' + this.get('transitionIn'));
            $submenu.one(Drupal.Exo.animationEvent + '.exo.menu.show', e => {
              $submenu.off(Drupal.Exo.animationEvent + '.exo.menu.show');
              $submenu.removeClass('exo-animate-' + this.get('transitionIn'));
              resolve(true);
            });
          }
          else {
            resolve(true);
          }
        }
      });
    }

    protected hide($item:JQuery, animate?:boolean):Promise<boolean> {
      return new Promise((resolve, reject) => {
        const $submenu = $item.find('> .exo-menu-level');
        this.$element.removeClass('exo-menu-shift');
        animate = animate !== false;
        if ($submenu.length) {
          this.open = false;
          if (animate && this.get('transitionOut') !== '' && Drupal.Exo.animationEvent !== undefined) {
            $submenu.off(Drupal.Exo.animationEvent + '.exo.menu.show');
            $item.removeClass('exo-animate exo-animate-in');
            $item.addClass('exo-animate exo-animate-out');
            $submenu.removeClass('exo-animate exo-animate-in exo-animate-' + this.get('transitionIn'));
            $submenu.addClass('exo-animate-' + this.get('transitionOut'));
            $submenu.one(Drupal.Exo.animationEvent + '.exo.menu.hide', e => {
              $item.removeClass('expand');
              $submenu.off(Drupal.Exo.animationEvent + '.exo.menu.hide');
              $submenu.removeClass('exo-animate-' + this.get('transitionOut'));
              $submenu.find('.expand').removeClass('expand');
              this.resize();
              resolve(true);
            });
          }
          else {
            $item.removeClass('expand');
            this.resize();
            resolve(true);
          }
        }
      });
    }
  }

  Drupal.ExoMenuStyles['mega_vertical'] = ExoMenuStyleMegaVertical;

})(jQuery);
