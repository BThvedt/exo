(function ($, Drupal, Swiper) {

  class ExoAlchemistEnhancementSlider {

    protected $wrapper:JQuery;
    protected id:string = '';
    protected swiper:any;

    constructor(id:string, $wrapper:JQuery) {
      this.$wrapper = $wrapper;
      this.id = id;
      const $pagination = $wrapper.find('.swiper-pagination');
      const $next = $wrapper.find('.swiper-button-next');
      const $prev = $wrapper.find('.swiper-button-prev');
      const $scrollbar = $wrapper.find('.swiper-scrollbar');
      const $autoplayTime = $wrapper.find('.swiper-autoplay-time');
      const $autoplayBar = $wrapper.find('.swiper-autoplay-bar');
      const isLayoutBuilder = this.isLayoutBuilder();
      const defaultSettings:any = {
        pagination: {},
        navigation: {},
        scrollbar: {},
        thumbs: {},
        autoplay: false,
        on: {
          init: () => {
            $(document).trigger('exoComponentSliderInit');
          },
          afterInit: (swiper) => {
            if ('ee-SliderHeightFirst' in swiper.el.dataset) {
              swiper.el.style.height = swiper.slides[0].offsetHeight + 'px';
            }
            setTimeout(() => {
              this.$wrapper.trigger('exoComponentSliderAfterInit', this.swiper);
            });
          },
          beforeResize: (swiper) => {
            if ('ee-SliderHeightFirst' in swiper.el.dataset) {
              swiper.el.style.height = null;
            }
          },
          resize: (swiper) => {
            if ('ee-SliderHeightFirst' in swiper.el.dataset) {
              swiper.el.style.height = swiper.slides[0].offsetHeight + 'px';
            }
          },
        }
      };
      if ($pagination.length) {
        defaultSettings['pagination']['el'] = $pagination.get(0);
      }
      if ($next.length) {
        defaultSettings['navigation']['nextEl'] = $next.get(0);
      }
      if ($prev.length) {
        defaultSettings['navigation']['prevEl'] = $prev.get(0);
      }
      if ($scrollbar.length) {
        defaultSettings['scrollbar']['el'] = $scrollbar.get(0);
      }

      if ($autoplayTime.length || $autoplayBar.length) {
        defaultSettings.autoplay = {};
        defaultSettings.autoplay['delay'] = 4000;
        defaultSettings.autoplay['disableOnInteraction'] = false;
        defaultSettings.on['autoplayStop'] = (slider) => {
          $autoplayTime.hide();
          $autoplayBar.parent().hide();
        }
        defaultSettings.on['autoplayTimeLeft'] = (slider, time, progress) => {
          if ($autoplayTime.length) {
            $autoplayTime[0].style.setProperty("--progress", String(1 - progress));
            $autoplayTime[0].textContent = `${Math.ceil(time / 1000)}s`;
          }
          if ($autoplayBar.length) {
            const percent = Math.min(100, Math.max(0, 100 - Math.round(progress * 100)));
            $autoplayBar.css('width', percent + '%');
          }
        };
      }

      const settings = $.extend(true, {}, defaultSettings, this.$wrapper.data('ee--slider-settings') || {});

      const $navSlider = $wrapper.closest('.exo-component').find('.ee--slider-nav[data-ee--slider-id="' + id + '"]');
      if ($navSlider.length) {
        const navSettings = $.extend(true, {}, {
          spaceBetween: 10,
          slidesPerView: $navSlider.find('.swiper-slide').length,
          freeMode: true,
          watchSlidesProgress: true,
        }, $navSlider.data('ee--slider-settings') || {});
        const navSwiper = new Swiper($navSlider.get(0), navSettings);
        settings['thumbs']['swiper'] = navSwiper;
      }

      this.swiper = new Swiper(this.$wrapper.get(0), settings);
      if (typeof Drupal.behaviors.exoImagine != 'undefined') {
        this.swiper.on('slideChange', () => {
          Drupal.behaviors.exoImagine.render(this.$wrapper);
        });
      }

      if (isLayoutBuilder) {
        this.buildForLayoutBuilder();
      }
    }

    protected buildForLayoutBuilder():void {
      if (this.swiper.params.autoplay.enabled) {
        $(document).on('exoComponentActive.exo.alchemist.enhancement.slider.' + this.id, (e, element) => {
          if (Drupal.ExoAlchemistAdmin.getActiveComponent().find(this.$wrapper).length) {
            this.swiper.autoplay.pause();
          }
        });
        $(document).on('exoComponentInactive.exo.alchemist.enhancement.slider.' + this.id, (e, element) => {
          if (Drupal.ExoAlchemistAdmin.getActiveComponent().find(this.$wrapper).length) {
            this.swiper.autoplay.resume();
          }
        });
      }
      $(document).on('exoComponentOps.exo.alchemist.enhancement.slider.' + this.id, (e, element) => {
        if (Drupal.ExoAlchemistAdmin.getActiveComponent().find(this.$wrapper).length) {
          let $element = $(element);
          $element.find('.exo-field-op-rotator-prev').off('click').on('click', e => {
            e.preventDefault();
            Drupal.ExoAlchemistAdmin.setFieldInactive();
            this.swiper.slidePrev();
          });
          $element.find('.exo-field-op-rotator-next').off('click').on('click', e => {
            e.preventDefault();
            Drupal.ExoAlchemistAdmin.setFieldInactive();
            this.swiper.slideNext();
          });
        }
      });

      $(document).on('exoComponentFieldEditActive.exo.alchemist.enhancement.slider.' + this.id, (e, element) => {
        const $element = $(element);
        if (!this.$wrapper.find($element).length || $element.hasClass('swiper-slide-active')) {
          return;
        }
        const slide = $element.get(0);
        if (!slide) {
          return;
        }
        // Loop mode rearranges slides within the DOM, so a DOM index is not a
        // usable slide index. Resolving against Swiper's own list also rules
        // out slides belonging to an instance that has been destroyed.
        const index = this.swiper && this.swiper.slides
          ? Array.prototype.indexOf.call(this.swiper.slides, slide)
          : -1;
        // A slideTo() that cannot move fires no transition, which would leave
        // the pointer event lock below permanently engaged.
        if (index < 0 || index === this.swiper.activeIndex) {
          return;
        }
        let finished = false;
        const finish = () => {
          if (finished) {
            return;
          }
          finished = true;
          this.swiper.off('slideChangeTransitionEnd', finish);
          Drupal.ExoAlchemistAdmin.unlockTargetPointerEvents();
          // A later Prev/Next can resolve this callback instead of the
          // transition it was registered for, so only claim the selection while
          // the requested slide is on screen and nothing else has claimed it.
          if (slide.classList.contains('swiper-slide-active') && !Drupal.ExoAlchemistAdmin.getActiveField()) {
            Drupal.ExoAlchemistAdmin.setFieldActive($(slide));
          }
        };
        Drupal.ExoAlchemistAdmin.lockTargetPointerEvents();
        Drupal.ExoAlchemistAdmin.setFieldInactive();
        this.swiper.once('slideChangeTransitionEnd', finish);
        setTimeout(finish, (this.swiper.params.speed || 300) + 500);
        this.swiper.slideTo(index);
      });
    }

    public unload() {
      $(document).off('exoComponentOps.exo.alchemist.enhancement.slider.' + this.id);
      $(document).off('exoComponentFieldEditActive.exo.alchemist.enhancement.slider.' + this.id);
      this.swiper.destroy();
    }

    protected isLayoutBuilder() {
      return Drupal.ExoAlchemistAdmin && Drupal.ExoAlchemistAdmin.isLayoutBuilder();
    }

  }

  /**
   * eXo Alchemist enhancement behavior.
   */
  Drupal.behaviors.exoAlchemistEnhancementSlider = {
    count: 0,
    instances: {},
    attach: function(context) {
      var self = this;
      $('.ee--slider-wrapper', context).once('exo.alchemist.enhancement').each(function () {
        const $wrapper = $(this);
        const id = $wrapper.data('ee--slider-id');
        $wrapper.data('ee--slider-count', self.count);
        self.instances[id + self.count] = new ExoAlchemistEnhancementSlider(id, $wrapper);
        self.count++;
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger === 'unload') {
        var self = this;
        $('.ee--slider-wrapper', context).each(function () {
          const $wrapper = $(this);
          const id = $wrapper.data('ee--slider-id') + $wrapper.data('ee--slider-count');
          if (typeof self.instances[id] !== 'undefined') {
            self.instances[id].unload();
            delete self.instances[id];
          }
        });
      }
    }
  }

})(jQuery, Drupal, Swiper);
