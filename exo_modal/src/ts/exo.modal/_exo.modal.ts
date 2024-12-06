interface ExoModalProcessBarInterface {
  currentTime?:number;
  maxHideTime?:number;
  hideEta?:number;
  el?:JQuery;
  updateProgress?:Function;
}

class ExoModal extends ExoData {
  protected label:string = 'Modal';
  protected doDebug:boolean = false;
  protected defaults:ExoSettingsGroupInterface = {
    preset: null,
    title: '',
    hideTitle: false,
    subtitle: '',
    overlayColor: 'rgba(0, 0, 0, 0.4)',
    icon: null,
    iconText: null,
    rtl: false,
    inherit: false,
    width: 600,
    height: null,
    top: null,
    bottom: null,
    right: null,
    left: null,
    borderBottom: true,
    padding: 0,
    ajax: null,
    contentAjax: null,
    contentAjaxCache: true,
    contentSelector: null,
    radius: 3,
    zindex: 999,
    iframe: false,
    iframeHeight: 400,
    iframeWidth: 800,
    iframeResponsive: false,
    iframeURL: null,
    imageUrl: null,
    focusInput: true,
    group: '',
    nest: false,
    loop: false,
    arrowKeys: true,
    navigateCaption: true,
    navigateArrows: true, // Boolean, 'closeToModal', 'closeScreenEdge'
    navigateTitles: false,
    history: false,
    restoreDefaultContent: false,
    destroyOnClose: false,
    autoOpen: 0, // Boolean, Number
    bodyOverflow: true,
    fullscreen: false,
    openFullscreen: false,
    openTall: false,
    closeOnEscape: true,
    closeButton: true,
    closeInBody: false,
    smartActions: true,
    appendTo: Drupal.ExoModal.globalWrapper, // or false
    appendToOverlay: Drupal.ExoModal.globalWrapper, // or false
    appendToNavigate: Drupal.ExoModal.globalWrapper, // or false
    class: '',
    appendToClosest: false, // or false
    overlay: true,
    overlayClose: true,
    timeout: false,
    timeoutProgressbar: false,
    pauseOnHover: false,
    transitionIn: 'comingIn', // comingIn, bounceInDown, bounceInUp, fadeInDown, fadeInUp, fadeInLeft, fadeInRight, flipInX
    transitionOut: 'comingOut', // comingOut, bounceOutDown, bounceOutUp, fadeOutDown, fadeOutUp, , fadeOutLeft, fadeOutRight, flipOutX
    transitionInOverlay: 'fadeIn',
    transitionOutOverlay: 'fadeOut',
    transitionInPanel: 'fadeInLeft',
    transitionOutPanel: 'fadeOutLeft',
    onFullscreen: function () {},
    onResize: function () {},
    onOpening: function () {},
    onOpened: function () {},
    onClosing: function () {},
    onClosed: function () {},
    afterRender: function () {}
  };
  protected inheritExclude:Array<string> = [
    'id',
    'inherit',
    'title',
    'subtitle',
    'icon',
    'iconText',
    'ajax',
    'contentAjax',
    'iframeUrl',
    '_exoTime',
  ];
  protected readonly events = {
    opening: new ExoEvent<ExoModal>(),
    opened: new ExoEvent<ExoModal>(),
    closing: new ExoEvent<ExoModal>(),
    closed: new ExoEvent<ExoModal>()
  };
  protected readonly states:ExoSettingsGroupInterface = {
    CLOSING: 'closing',
    CLOSED: 'closed',
    OPENING: 'opening',
    OPENED: 'opened',
    DESTROYED: 'destroyed'
  };
  protected data:any;
  protected name:string = 'exo-modal';
  protected class:string;
  protected content:string;
  protected built:boolean = false;
  protected state:string;
  protected width:number = 0;
  protected widthOffset:number = 0;
  protected timer:ReturnType<typeof setTimeout> = null;
  protected timerTimeout:ReturnType<typeof setTimeout> = null;
  protected timerInterval:number = null;
  protected progressBar:ExoModalProcessBarInterface = null;
  protected isPaused:boolean = false;
  protected isFullscreen:boolean = false;
  protected isCompressed:boolean = false;
  protected isTall:boolean = false;
  protected isSetHeight:boolean = false;
  protected headerHeight:number = 0;
  protected footerHeight:number = 0;
  protected modalHeight:number = 0;
  protected autoOpenProcessed:boolean = false;
  protected panelOpen:boolean = false;
  protected $trigger:JQuery;
  protected $element:JQuery;
  protected $contentAjaxPlaceholder:JQuery;
  protected ajaxLoaded:boolean = false;
  protected contentAjaxLoaded:boolean = false;
  protected imageLoaded:boolean = false;
  protected $overlay:JQuery;
  protected $navigate:JQuery;
  protected $wrap:JQuery;
  protected $header:JQuery;
  protected $footer:JQuery;
  protected $content:JQuery;
  protected $contentSelection:JQuery;
  protected $contentPlaceholder:JQuery;
  protected $sectionHeader:JQuery;
  protected $sectionFooter:JQuery;

  public build(data):Promise<ExoSettingsGroupInterface> {
    return new Promise((resolve, reject) => {
      const dataClass = [];
      if (data.class) {
        dataClass.push(data.class);
      }
      if (data.preset && typeof drupalSettings.exoModal.presets === 'object' && drupalSettings.exoModal.presets[data.preset]) {
        data = jQuery.extend(true, {}, drupalSettings.exoModal.presets[data.preset], data);
        if (drupalSettings.exoModal.presets[data.preset].class) {
          dataClass.push(drupalSettings.exoModal.presets[data.preset].class);
        }
      }
      data = jQuery.extend(true, {}, Drupal.ExoModal.getSettingsGroup('defaults'), data);
      data.class = dataClass.join(' ');
      super.build(data, false).then(data => {
        if (data !== null) {
          this.debug('log', 'Build: Act', '[' + this.id + ']', data);
          const $trigger = this.getTriggerElement().once('exo.modal');
          if ($trigger.length) {
            this.debug('log', 'Build: Bind Trigger', '[' + this.id + ']');
            this.bindTrigger();
          }
          const $element = this.getElement().once('exo.modal');
          if ($element.length) {
            this.debug('log', 'Build: Found Element', '[' + this.id + ']');
            if (this.get('contentAjax')) {
              this.$contentAjaxPlaceholder = $('<div class="exo-modal-ajax-placeholder hidden" />').insertBefore($element);
            }
          }
          // We resolve true if either the trigger or the element has been found.
          // This will only occur once per trigger/element as we use .once(). We
          // do this due to ajax as the trigger may exist but the modal element
          // may not yet.
          if ($trigger.length || $element.length) {
            this.state = this.states.CLOSED;
            resolve(data);
          }
          else {
            // Resolving FALSE we leave this in drupalSettings and let it process
            // again. The reason for this is this can be called before the element
            // has actually been added to the page.
            resolve(null);
          }
        }
        else {
          resolve(data);
        }
      }, reject);
    });
  }

  public afterBuild() {
    if (this.getElement().length) {
      if (this.get('autoOpen')) {
        if (Drupal.Exo.isInitialized()) {
          setTimeout(() => {
            this.open();
          });
        }
        else {
          Drupal.Exo.event('ready').on('exo.modal.' + this.getId(), () => {
            Drupal.Exo.event('ready').off('exo.modal.' + this.getId());
            this.open();
          });
        }
      }
      else if (this.getElement().find('.exo-form-container-error').length) {
        this.open();
        const $messages = $('.messages.error');
        if ($messages.length && !$messages.closest('.exo-modal').length) {
          this.$content.prepend($messages);
        }
      }
    }
  }

  protected rebuildContent() {
    this.built = false;
    return this;
  }

  public refreshContent() {
    if (this.$content.find('.messages').length) {
      this.$wrap.scrollTop(0);
    }
    this.bindClose();
    // If we have a focused modal we shoudl account for changes.
    this.createFooter();
  }

  protected buildContent() {
    if (this.$element && this.$element.length && !this.built) {
      this.built = true;
      this.debug('log', 'Build', '[' + this.getId() + ']', this.getData());

      if (this.get('inherit') === true) {
        // Inherit will use some settings from currently active modal.
        const focusedModal = Drupal.ExoModal.getVisibleFocus();
        if (focusedModal) {
          for (let i = this.$element[0].classList.length - 1; i >= 0; i--) {
            const className = this.$element[0].classList[i];
            if (className.startsWith('exo-modal-theme-')) {
              this.$element[0].classList.remove(className);
            }
          }
          for (let i = focusedModal.$element[0].classList.length - 1; i >= 0; i--) {
            const className = focusedModal.$element[0].classList[i];
            if (className.startsWith('exo-modal-theme-')) {
              this.$element.addClass(className);
            }
          }
          jQuery.each(focusedModal.getData(), (index, val) => {
            if (this.inheritExclude.indexOf(String(index)) === -1) {
              this.data[index] = val;
            }
          });
          this.set('zindex', focusedModal.get('zindex') + 1);
        }
      }
      if (this.$element.hasClass(this.name + '-hidden')) {
        this.$element.removeClass(this.name + '-hidden');
      }
      if (this.get('class') !== '') {
        this.$element.addClass(this.get('class'));
      }
      const $form = this.$element.closest('.exo-form');
      if ($form.length) {
        for (let i = $form[0].classList.length - 1; i >= 0; i--) {
          const className = $form[0].classList[i];
          if (className.startsWith('exo-form')) {
            this.$element.addClass(className);
          }
        }
      }
      this.class = (this.$element.attr('class') !== undefined) ? this.$element.attr('class') : '';
      this.content = this.$element.html();
      this.$overlay = $('<div class="' + this.name + '-overlay" style="background-color:' + this.get('overlayColor') + '"></div>');
      this.$navigate = $('<div class="' + this.name + '-navigate"><div class="' + this.name + '-navigate-caption">Use</div><button class="' + this.name + '-navigate-prev"></button><button class="' + this.name + '-navigate-next"></button></div>');
      this.$element.attr('aria-hidden', 'true');
      this.$element.attr('aria-labelledby', this.getId());
      this.$element.attr('role', 'dialog');

      this.$content = this.$element.find('.' + this.name + '-content').first();
      this.$sectionHeader = this.$element.find('.' + this.name + '-section-header');
      this.$sectionFooter = this.$element.find('.' + this.name + '-section-footer');

      // Nested forms should use modal theme.
      const $formsWithTheme = $('form[class*="exo-form-theme-"]', this.$content);
      if ($formsWithTheme.length) {
        let modalTheme = null;
        for (let i = this.$element[0].classList.length - 1; i >= 0; i--) {
          const className = this.$element[0].classList[i];
          if (className.startsWith('exo-modal-theme-content-')) {
            modalTheme = className.replace('exo-modal-theme-content-', '');
          }
        }
        if (modalTheme) {
          $('form[class*="exo-form-theme-"]', this.$content).removeClass(function (index, className) {
            return (className.match(/(^|\s)exo-form-theme-\S+/g) || []).join(' ');
          });
          $formsWithTheme.addClass('exo-form-theme-' + modalTheme);
        }
      }

      if (!this.$element.hasClass(this.name)) {
        this.$element.addClass(this.name);
      }

      if (this.get('group') !== '') {
        this.$element.attr('data-' + this.name + '-group', this.get('group'));
      }
      if (this.get('loop') === true) {
        this.$element.attr('data-' + this.name + '-loop', 'true');
      }

      if (this.get('appendTo') !== false) {
        if (this.get('appendToClosest') === true) {
          if (this.get('appendTo') === 'form') {
            this.$element.prependTo(this.$element.closest(this.get('appendTo')));
          }
          else {
            this.$element.appendTo(this.$element.closest(this.get('appendTo')));
          }
        }
        else {
          this.$element.appendTo(this.get('appendTo'));
        }
        // If modal is within eXo content, we need to offset it so that it
        // accounts for displacement.
        if (this.$element.closest('.exo-content').length) {
          this.$element.css({
            top: displace.offsets.top,
            bottom: displace.offsets.bottom,
            left: displace.offsets.left,
            right: displace.offsets.right,
          });
        }
      }

      if (this.get('iframe') === true) {
        this.$content.html('<iframe class="' + this.name + '-iframe"></iframe>');

        if (this.get('iframeHeight') !== null) {
          if (this.get('iframeHeight') !== null && this.get('iframeWidth') !== null && this.get('iframeResponsive') === true) {
            const paddingBottom = this.getIframeResponsivePadding();
            this.$content.find('.' + this.name + '-iframe').css('width', '100%').wrap('<div class="' + this.name + '-iframe-responsive" />');
            this.$content.find('.' + this.name + '-iframe-responsive').css('padding-bottom', paddingBottom + '%');
          }
          else {
            this.$content.find('.' + this.name + '-iframe').css('height', this.get('iframeHeight'));
          }
        }
      }

      this.$wrap = this.$element.find('.' + this.name + '-wrap').first();

      if (this.get('zindex') !== null) {
        this.setZindex(this.get('zindex'));
      }

      if (this.get('radius') !== '') {
        this.$element.css('border-radius', this.get('radius'));
      }

      if (this.get('padding') !== '' && this.get('padding') !== 0) {
        this.$content.css('padding', this.get('padding'));
      }

      if (this.get('rtl') === true) {
        this.$element.addClass(this.name + '-rtl');
      }

      if (this.get('openFullscreen') !== false) {
        this.isFullscreen = true;
        this.$element.addClass('isFullscreen');
      }

      if (this.get('height')) {
        this.isSetHeight = true;
        this.$element.addClass('isSetHeight');
      }

      if (this.get('openTall') === true) {
        this.isTall = true;
        this.$element.addClass('isTall');
      }

      if (this.get('closeInBody') !== false) {
        const $close = $('<div class="exo-container-button ' + this.get('closeInBody') + '"><a href="javascript:void(0)" class="' + this.name + '-button ' + this.name + '-button-close" data-' + this.name + '-close></a></div>');
        let $region = this.$element.find('.' + this.name + '-container').first();
        let $header = this.$element.find('.' + this.name + '-section-header').first();
        if ($header.length) {
          $region = $header;
        }
        $region.prepend($close);
        setTimeout(() => {
          this.widthOffset += $close.outerWidth();
        });
      }

      this.createHeader();
      this.createFooter();
      this.buildPanels();
      this.recalcSize();
      this.recalcVerticalPos();
      this.recalcHorizontalPos();
      this.callCallback('afterRender');
    }
  }

  protected setZindex(zindex:number) {
    zindex = zindex + (Drupal.ExoModal.getVisible(true).count() * 3);
    this.set('zindex', zindex);
    this.$overlay.css('z-index', zindex - 2);
    this.$navigate.css('z-index', zindex - 1);
    this.$element.css('z-index', zindex);
  }

  protected createHeader() {
    // This can be called multiple times and should be rebuilt each time.
    this.$element.find('.' + this.name + '-header').remove();

    const title = this.get('hideTitle') === true ? '' : this.get('title');
    this.$header = $('<div class="' + this.name + '-header"><h2 class="' + this.name + '-header-title">' + title + '</h2><p class="' + this.name + '-header-subtitle"><span>' + this.get('subtitle') + '</span></p><div class="' + this.name + '-header-buttons"></div></div>');

    if (this.get('subtitle') === '') {
      this.$header.addClass(this.name + '-no-subtitle');
    }

    if (this.get('fullscreen') === true) {
      this.$header.find('.' + this.name + '-header-buttons').append('<a href="javascript:void(0)" class="' + this.name + '-button ' + this.name + '-button-fullscreen" data-' + this.name + '-fullscreen></a>');
    }

    if (this.get('closeButton') === true) {
      this.$header.find('.' + this.name + '-header-buttons').append('<a href="javascript:void(0)" class="' + this.name + '-button ' + this.name + '-button-close" data-' + this.name + '-close></a>');
    }

    if (this.get('timeoutProgressbar') === true) {
      this.$header.prepend('<div class="' + this.name + '-progressbar"><div></div></div>');
    }

    this.$element.css('border-bottom-width', '');
    if (this.get('title') !== "" || this.get('closeButton') === true || this.get('fullscreen') || this.get('timeoutProgressbar')) {
      if (this.get('borderBottom') === true) {
        this.$element.css('border-bottom-width', '3px');
      }
      if (this.get('icon') !== null || this.get('iconText') !== null) {
        this.$header.prepend('<i class="' + this.name + '-header-icon"></i>');
        if (this.get('icon') !== null) {
          this.$header.find('.' + this.name + '-header-icon').addClass(this.get('icon'));
        }
        if (this.get('iconText') !== null) {
          this.$header.find('.' + this.name + '-header-icon').html(this.get('iconText'));
        }
      }
      if (!this.get('closeInBody')) {
        this.$element.css('overflow', 'hidden').prepend(this.$header);
      }
    }
  }

  public createFooter() {
    // This can be called multiple times and should be rebuilt each time.
    this.$element.find('.' + this.name + '-footer').remove();
    this.$element.removeClass('has-footer');

    this.$footer = $('<div class="' + this.name + '-footer"></div>');
    if (this.get('smartActions') === true) {
      const $actions = this.$element.find('.form-actions');
      if ($actions.length) {
        $actions.last().addClass('exo-modal-actions');
      }
      else {
        this.$element.find('.exo-form-container-button--primary').addClass('exo-modal-actions');
      }
      $actions.removeClass('exo-form-element');

      $('.exo-modal-views-view .exo-form-container-form-actions').addClass('exo-modal-actions');
      const $inputs = this.$element.find('.exo-modal-actions:last input, .exo-modal-actions button, .exo-modal-actions a');
      if ($inputs.length) {
        this.$element.addClass('has-footer');
        this.$element.css('border-bottom-width', 0);
        this.set('borderBottom', false);
        if ($inputs.length) {

          const onClick = (e, $original) => {
            const text = $original.text().toLowerCase();
            if ($original.is('a')) {
              if (text === 'cancel' || $original.is('[data-' + this.name + '-close]')) {
                this.close();
              }
              else {
                window.location.href = $original.attr('href');
              }
            }
            else {
              $original.trigger('mousedown').trigger('mouseup').trigger('click');
            }
          }
          const $primary = $inputs.filter('.button--primary');
          $inputs.each((index, element) => {
            const $original = $(element);
            const isPrimary = $primary.length ? $original.hasClass('button--primary') : index === 0;
            $original.clone()
              .attr('id', $original.attr('id') ? $original.attr('id') + '-clone' : '')
              .attr('name', $original.attr('name') ? $original.attr('name') + '-clone' : '')
              .attr('data-drupal-selector', $original.attr('data-drupal-selector') ? $original.attr('data-drupal-selector') + '-clone' : '')
              .removeAttr('class')
              .addClass('exo-modal-action' + (isPrimary ? ' primary': ''))
              .on('click', e => {
                e.preventDefault();
                const buttonDelay = $original.data('exo-modal-action-delay');
                if (buttonDelay) {
                  if (typeof this.events[buttonDelay] !== 'undefined') {
                    this.event(buttonDelay).on('exo.modal.footer.' + index, e => {
                      this.event(buttonDelay).off('exo.modal.footer.' + index);
                      onClick(e, $original);
                    });
                  }
                  else if (buttonDelay === parseInt(buttonDelay)) {
                    setTimeout(() => {
                      onClick(e, $original);
                    }, buttonDelay);
                  }
                }
                else {
                  setTimeout(() => {
                    onClick(e, $original);
                  });
                }
              }).appendTo(this.$footer);
          });
          $inputs.closest('.exo-form-element-type-actions').addClass('exo-modal-hide');
          $inputs.closest('.exo-modal-actions').addClass('exo-modal-hide');
          $inputs.closest('.exo-form-container-exo-modal-actions').addClass('exo-modal-hide');
        }
      }
    }

    const groupModals = Drupal.ExoModal.getGrouped(this.get('group'));
    if (groupModals.count() > 1) {
      if (this.get('navigateTitles') === true) {
        const prevModal = groupModals.getPrev(this.getId(), this.get('loop'));
        const nextModal = groupModals.getNext(this.getId(), this.get('loop'));
        const $navigateTitles = $('<div class="exo-modal-navigate-titles"><a href="javascript:void(0)" class="exo-modal-navigate-title-prev" data-' + this.name + '-prev></a><a href="javascript:void(0)" class="exo-modal-navigate-title-next" data-' + this.name + '-next></a></div>');
        const $prevTitle = $('.exo-modal-navigate-title-prev', $navigateTitles).hide();
        const $nextTitle = $('.exo-modal-navigate-title-next', $navigateTitles).hide();
        if (prevModal) {
          $prevTitle.text(prevModal.get('title') || 'Prev').fadeIn();
        }
        if (nextModal) {
          $nextTitle.text(nextModal.get('title') || 'Next').fadeIn();
        }

        $navigateTitles.appendTo(this.$footer);
      }
    }

    if (this.$footer.children().length) {
      this.$element.append(this.$footer);
    }
  }

  protected buildPanels() {
    const $panels = this.$element.find('.exo-modal-panel-trigger').once('exo.modal');
    if ($panels.length) {
      $panels.each((index, element) => {
        const $trigger = $(element);
        const id = $trigger.data('exo-modal-panel');
        const $pane = this.$element.find('.exo-modal-panel[data-exo-modal-panel=' + id + ']').hide();
        $trigger.find('.hide').hide();
        $trigger.on('click.exo.modal', e => {
          e.preventDefault();
          if ($trigger.hasClass('active')) {
            this.hidePanel($trigger);
          }
          else {
            this.hidePanels();
            this.showPanel($trigger);
          }
        });
      });
      this.event('closed').on('exo.modal.panels', e => {
        this.hidePanels(false);
      });
    }
  }

  protected showPanel($trigger:JQuery, animate?:boolean) {
    this.panelOpen = true;
    const id = $trigger.data('exo-modal-panel');
    const $pane = this.$element.find('.exo-modal-panel[data-exo-modal-panel=' + id + ']');
    $trigger.data('exo-modal-width', this.get('width'));
    const newWidth = $trigger.data('exo-modal-panel-width');
    if (newWidth) {
      this.setWidth(newWidth);
    }
    const transitionIn = this.get('transitionInPanel');
    const transitionOut = this.get('transitionOutPanel');
    if (animate !== false && transitionIn && transitionOut && Drupal.Exo.animationEvent !== undefined) {
      $pane.show().addClass('transitionIn exo-animate-' + transitionIn);
      this.$content.addClass('transitionOut exo-animate-' + transitionOut);

      $pane.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
        if ($pane.get(0) === e.currentTarget) {
          $pane.off(Drupal.Exo.animationEvent + '.exo.modal');
          $pane.removeClass('exo-animate-' + transitionIn + ' transitionIn');
          this.$content.removeClass('exo-animate-' + transitionOut + ' transitionOut');
          $pane.addClass('active');
        }
      });

      $trigger.addClass('transitionOut exo-animate-' + transitionOut);
      $trigger.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
        if ($trigger.get(0) === e.currentTarget) {
          $trigger.off(Drupal.Exo.animationEvent + '.exo.modal');
          $trigger.removeClass('transitionOut exo-animate-' + transitionOut);
          $trigger.find('.show').hide();
          $trigger.find('.hide').show();
          $trigger.addClass('active');
          $trigger.addClass('transitionIn exo-animate-' + transitionIn);
          $trigger.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
            if ($trigger.get(0) === e.currentTarget) {
              $trigger.off(Drupal.Exo.animationEvent + '.exo.modal');
            }
          });
        }
      });
    }
    else {
      $trigger.find('.show').hide();
      $trigger.find('.hide').show();
      $trigger.removeClass('active');
      $pane.show().addClass('active');
    }
  }

  protected hidePanel($trigger:JQuery, animate?:boolean) {
    this.panelOpen = false;
    const id = $trigger.data('exo-modal-panel');
    const $pane = this.$element.find('.exo-modal-panel[data-exo-modal-panel=' + id + ']');
    this.setWidth($trigger.data('exo-modal-width'));
    const transitionIn = this.get('transitionInPanel');
    const transitionOut = this.get('transitionOutPanel');
    if (animate !== false && transitionIn && transitionOut && Drupal.Exo.animationEvent !== undefined) {
      $pane.addClass('transitionOut exo-animate-' + transitionOut);
      this.$content.addClass('transitionIn exo-animate-' + transitionIn);
      $pane.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
        if ($pane.get(0) === e.currentTarget) {
          $pane.off(Drupal.Exo.animationEvent + '.exo.modal');
          $pane.removeClass('exo-animate-' + transitionOut  + ' transitionOut');
          this.$content.removeClass('exo-animate-' + transitionIn + ' transitionIn');
          $pane.hide().removeClass('active');
        }
      });

      $trigger.addClass('transitionOut exo-animate-' + transitionOut);
      $trigger.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
        if ($trigger.get(0) === e.currentTarget) {
          $trigger.off(Drupal.Exo.animationEvent + '.exo.modal');
          $trigger.removeClass('transitionOut exo-animate-' + transitionOut);
          $trigger.find('.show').show();
          $trigger.find('.hide').hide();
          $trigger.removeClass('active');
          $trigger.addClass('transitionIn exo-animate-' + transitionIn);
          $trigger.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
            if ($trigger.get(0) === e.currentTarget) {
              $trigger.off(Drupal.Exo.animationEvent + '.exo.modal');
            }
          });
        }
      });
    }
    else {
      $trigger.find('.show').show();
      $trigger.find('.hide').hide();
      $trigger.removeClass('active');
      $pane.hide().removeClass('active');
    }
  }

  protected hidePanels(animate?:boolean) {
    if (this.hasOpenPanel()) {
      const $activeTrigger = this.$element.find('.exo-modal-panel-trigger.active');
      if ($activeTrigger.length) {
        this.hidePanel($activeTrigger, animate);
      }
    }
  }

  public hasOpenPanel():boolean {
    return this.panelOpen === true;
  }

  public set(key, value):any {
    super.set(key, value)
    switch (key) {
      case 'title':
      case 'closeButton':
      case 'fullscreen':
      case 'subtitle':
      case 'borderBottom':
      case 'icon':
      case 'iconText':
        this.createHeader();
        this.bindElement();
        break;
    }
    return this;
  }

  protected getIframeResponsivePadding() {
    let percent = 0;
    let width = parseInt(this.get('iframeWidth'));
    let height = parseInt(this.get('iframeHeight'));
    if (width > height) {
      percent = (height / width) * 100;
    }
    if (height > width) {
      percent = (width / height) * 100;
    }
    return percent;
  }

  public setWidth(width:string|number) {
    this.set('width', width);
    this.recalcSize();
  }

  protected recalcSize() {
    var windowWidth = Drupal.Exo.$window.width() - displace.offsets.left - displace.offsets.right;
    var modalWidth = (this.get('left') !== null && this.get('left') !== false && this.get('left') !== '') && (this.get('right') !== null && this.get('right') !== false && this.get('right') !== '') ? windowWidth - (Drupal.Exo.getMeasurementValue(this.get('left')) || 0) - (Drupal.Exo.getMeasurementValue(this.get('right')) || 0) : this.get('width');
    this.$element.css('max-width', modalWidth);
    var modalHeight = this.get('height');
    if (modalHeight) {
      this.$element.css('max-height', modalHeight);
    }
    if (windowWidth < modalWidth + this.widthOffset) {
      this.isCompressed = true;
      this.$element.addClass('isCompressed');
    }
    else {
      this.isCompressed = false;
      this.$element.removeClass('isCompressed');
    }
    if (Drupal.Exo.isIE()) {
      if (modalWidth.toString().split('%').length > 1) {
        modalWidth = this.$element.outerWidth();
      }
    }

    if (this.get('imageUrl')) {
      this.isSetHeight = true;
      this.$element.addClass('isSetHeight');
      const $image = this.$content.find('> img') as JQuery<HTMLImageElement>;
      var windowHeight = Drupal.Exo.$window.height() - displace.offsets.top - displace.offsets.bottom;
      var maxWindowWidth = windowWidth - 50;
      var maxWindowHeight = windowHeight - 50;
      var width = $image[0].naturalWidth;
      var height = $image[0].naturalHeight;
      var ratio;
      var maxWidth;
      var maxHeight;
      if (width > height) {
        ratio = height / width;
        maxWidth = maxWindowWidth;
        maxHeight = Math.round(maxWindowWidth * ratio);
        if (maxHeight > maxWindowHeight) {
          maxWidth = Math.round(maxWindowHeight / ratio);
          maxHeight = maxWindowHeight;
        }
      }
      else {
        ratio = width / height;
        maxWidth = Math.round(maxWindowHeight * ratio);
        maxHeight = maxWindowHeight;
        if (maxWidth > maxWindowWidth) {
          maxWidth = maxWindowWidth;
          maxHeight = Math.round(maxWindowWidth / ratio);
        }
      }
      this.$element.css('max-width',  maxWidth + 'px');
      this.$element.css('max-height', maxHeight + 'px');
    }
  }

  protected recalcVerticalPos(first?:boolean) {
    if (this.get('top') !== null && this.get('top') !== false) {
      this.$element.css('margin-top', this.get('top'));
      if (this.get('top') === 0) {
        this.$element.css({
          borderTopRightRadius: 0,
          borderTopLeftRadius: 0
        });
      }
    } else {
      if (first === false) {
        this.$element.css({
          marginTop: '',
          borderRadius: this.get('radius')
        });
      }
    }
    if (this.get('bottom') !== null && this.get('bottom') !== false) {
      this.$element.css('margin-bottom', this.get('bottom'));
      if (this.get('bottom') === 0) {
        this.$element.css({
          borderBottomRightRadius: 0,
          borderBottomLeftRadius: 0
        });
      }
    } else {
      if (first === false) {
        this.$element.css({
          marginBottom: '',
          borderRadius: this.get('radius')
        });
      }
    }
  }

  protected recalcHorizontalPos(first?:boolean) {
    if (this.get('left') !== null && this.get('left') !== false) {
      this.$element.css('margin-left', this.get('left'));
      if (this.get('left') === 0) {
        this.$element.css({
          borderTopLeftRadius: 0,
          borderBottomLeftRadius: 0,
        });
      }
    } else {
      if (first === false) {
        this.$element.css({
          marginLeft: '',
          borderRadius: this.get('radius')
        });
      }
    }
    if (this.get('right') !== null && this.get('right') !== false) {
      this.$element.css('margin-right', this.get('right'));
      if (this.get('right') === 0) {
        this.$element.css({
          borderTopRightRadius: 0,
          borderBottomRightRadius: 0
        });
      }
    } else {
      if (first === false) {
        this.$element.css({
          marginRight: '',
          borderRadius: this.get('radius')
        });
      }
    }
  }

  public toggle() {
    if (this.state == this.states.OPENED) {
      this.close();
    }
    if (this.state == this.states.CLOSED) {
      this.open();
    }
  }

  public shouldAutoOpen():boolean {
    return this.get('autoOpen') && this.autoOpenProcessed === false;
  }

  public autoOpen() {
    if (this.shouldAutoOpen() && this.$element.length) {
      this.debug('Auto Open', this);
      this.autoOpenProcessed = true;
      setTimeout(() => {
        this.open();
      });
    }
  }

  public callCallback(callbackName:any) {
    const setting = this.get(callbackName);
    if (setting) {
      if (typeof setting === 'string') {
        let callback = Drupal.Exo.stringToCallback(setting);
        if (callback) {
          callback.object[callback.function](this);
        }
      }
      else if (typeof setting === 'function' || typeof setting === 'object') {
        setting(this);
      }
    }
    // Trigger $window events.
    Drupal.Exo.$window.trigger('exo-modal:' + callbackName, [this]);
  }

  public toggleContentSelector() {
    if (this.get('contentSelector')) {
      if (this.state == this.states.CLOSED) {
        if (!this.$contentPlaceholder) {
          this.$contentPlaceholder = $('<div class="exo-modal-placeholder data-exo-modal-placeholder="' + this.get('contentSelector') + '"></div>');
        }
        this.$contentSelection = $(this.get('contentSelector'));
        if (this.$contentSelection.length) {
          this.$content = this.$element.find('.' + this.name + '-content');
          this.$contentPlaceholder.insertBefore(this.$contentSelection);
          this.$contentSelection.appendTo(this.$content);
        }
      }
      else {
        this.$contentSelection.insertBefore(this.$contentPlaceholder);
        this.$contentPlaceholder.remove();
      }
    }
  }

  public open(param?:any) {
    if (this.state == this.states.CLOSED) {
      this.debug('log', 'Open', '[' + this.getId() + ']', this.getData());
      let ajaxUrl = this.get('ajaxUrl');
      if (!ajaxUrl && this.get('ajax') || this.get('contentAjax')) {
        const route = this.get('ajax') ? this.get('ajax') : this.get('contentAjax');
        ajaxUrl = this.addDestination(Drupal.url(route));
        this.set('ajaxUrl', ajaxUrl);
      }
      if (ajaxUrl && this.ajaxLoaded === false && this.contentAjaxLoaded === false) {
        const self = this;
        let ajax = Drupal.ajax({
          progress: { type: 'fullscreen' },
          base: this.$trigger.attr('id'),
          element: this.$trigger[0],
          url: ajaxUrl,
          exoModal: this,
          exoModalParam: param,
        });
        const successCallback = ajax.options.success;
        ajax.options.success = function (response, status, xmlhttprequest) {
          // Set ajax request as loaded so we can continue.
          self.ajaxLoaded = true;
          // Do not load again if content cache is enabled.
          self.contentAjaxLoaded = self.get('contentAjaxCache') && !self.get('destroyOnClose');
          successCallback(response, status, xmlhttprequest);
        };
        ajax.execute();
        return;
      }

      if (this.get('imageUrl') && this.imageLoaded === false) {
        const $content = this.$element.find('.' + this.name + '-content').first();
        $content.html('<div class="' + this.name + '-content-loader' + '" style="height:80px"></div>');
        const $image = $('<img src="' + this.get('imageUrl') + '" style="display:block;width:100%;height:100%;" />').on('load', (e) => {
          this.imageLoaded = true;
          this.set('width', null);
          $content.html('');
          $content.append($image);
          this.open(param);
        });
        return;
      }

      // Prepare for next ajax request.
      this.ajaxLoaded = false;
      this.toggleContentSelector();
      this.buildContent();
      Drupal.ExoDisplace.calculate();
      if (this.get('nest') !== true) {
        Drupal.ExoModal.closeAll();
      }
      this.state = this.states.OPENING;
      this.$element.trigger(this.states.OPENING);
      this.$element.removeAttr('aria-hidden');

      if (this.get('timeoutProgressbar') === true) {
        this.$element.find('.' + this.name + '-progressbar > div').width('100%');
      }

      if (this.get('iframe') === true) {
        this.$content.addClass(this.name + '-content-loader');
        const $iframe = this.$element.find('.' + this.name + '-iframe');
        $iframe.on('load.exo.modal', e => {
          const iframe = e.target as HTMLIFrameElement;
          $iframe.parent().removeClass(this.name + '-content-loader');
          iframe.contentWindow.focus();
        });

        var href = null;
        try {
          href = $(param.currentTarget).attr('href') !== '' ? $(param.currentTarget).attr('href') : null;
        } catch (e) { /* console.warn(e); */ }

        if ((this.get('iframeURL') !== null) && (href === null || href === undefined)) {
          href = this.get('iframeURL');
        }
        if (href === null || href === undefined) {
          throw new Error('Failed to find iframe URL');
        }
        $iframe.attr('src', href);
      }
    }

    if (this.get('bodyOverflow') || Drupal.Exo.isMobile()) {
      if (Drupal.ExoModal.getVisible().count() === 1) {
        Drupal.Exo.lockOverflow(this.$wrap);
      }
    }

    this.doOpen(param);

    if (this.get('timeout') !== false && !isNaN(parseInt(this.get('timeout'))) && this.get('timeout') !== false && this.get('timeout') !== 0) {
      this.startProgress(this.get('timeout'));
    }

    // Close on overlay click
    if (this.get('overlayClose') && !this.$element.hasClass(this.get('transitionOut'))) {
      this.$overlay.on('click.exo.modal', () => {
        this.close();
      });
    }

    const updateTimer = () => {
      this.recalcLayout();
      this.timer = setTimeout(updateTimer, 300);
    }
    setTimeout(() => {
      updateTimer();
    });
  }

  public doOpen(param?:any) {
    this.debug('log', 'Do Open', '[' + this.getId() + ']', param);
    this.$trigger.trigger('blur');
    if (!this.$element.closest('#exo-modals').length) {
      $('.exo-fixed-float:not(.exo-fixed-not-invisible)').addClass('exo-fixed-invisible');
    }

    if (this.get('overlay') === true) {
      if (this.get('appendToOverlay') === false) {
        this.$overlay.prependTo(this.$element.parent());
      } else {
        if (this.get('appendToClosest') === true) {
          this.$overlay.prependTo(this.$element.closest(this.get('appendToOverlay')));
        }
        else {
          this.$overlay.prependTo(this.get('appendToOverlay'));
        }
        // If modal is within eXo content, we need to offset it so that it
        // accounts for displacement.
        if (this.$overlay.closest('.exo-content').length) {
          this.$overlay.css({
            top: displace.offsets.top,
            bottom: displace.offsets.bottom,
            left: displace.offsets.left,
            right: displace.offsets.right,
          });
        }
      }
    }

    var transitionIn = this.get('transitionIn');
    var transitionInOverlay = this.get('transitionInOverlay');
    var previousModal = null;

    if (typeof param == 'object') {

      if (param.transition !== undefined || param.transitionIn !== undefined) {
        transitionIn = param.transition || param.transitionIn;
      }
      if (param.zindex !== undefined) {
        this.setZindex(param.zindex);
      }
      if (param.previousModal !== undefined) {
        previousModal = param.previousModal;
      }
    }

    if (previousModal) {
      previousModal.getOverlay().hide();
      this.$overlay.show();
      transitionInOverlay = null;
    }

    if (transitionInOverlay) {
      this.$overlay.addClass(transitionInOverlay);
    }

    if (this.get('class') !== '') {
      this.$overlay.addClass(this.get('class') + '-overlay');
    }

    // Bind behaviors. When using exoModalInsert ajax this has not yet happened.
    // Drupal.attachBehaviors(this.$element.get(0), drupalSettings);
    if (transitionIn !== '' && Drupal.Exo.animationEvent !== undefined) {
      this.$element.addClass('transitionIn ' + transitionIn).css('display', 'flex');
      this.event('opening').trigger(this);
      this.callCallback('onOpening');
      this.$wrap.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
        if (this.$wrap.get(0) === e.currentTarget) {
          this.$wrap.off(Drupal.Exo.animationEvent + '.exo.modal');
          this.$element.removeClass(transitionIn + ' transitionIn');
          this.$overlay.removeClass(transitionInOverlay);
          this.$navigate.removeClass('fadeIn');
          this.opened(param);
        }
      });
    } else {
      this.$element.css('display', 'flex');
      this.event('opening').trigger(this);
      this.callCallback('onOpening');
      this.opened(param);
    }

    this.openNav(param);

    if (this.get('pauseOnHover') === true && this.get('pauseOnHover') === true && this.get('timeout') !== false && !isNaN(parseInt(this.get('timeout'))) && this.get('timeout') !== false && this.get('timeout') !== 0) {
      this.$element.off('mouseenter')
        .on('mouseenter.exo.modal', e => {
          e.preventDefault();
          this.isPaused = true;
        });
      this.$element.off('mouseleave')
        .on('mouseleave.exo.modal', e => {
          e.preventDefault();
          this.isPaused = false;
        });
    }

    setTimeout(() => {
      this.$wrap.scrollTop(0);
      if (this.get('iframe') !== true) {
        // Focus on the first field
        if (this.get('focusInput')) {
          this.$element.find(':input:not(button):enabled:visible:first').trigger('focus').filter('input:text').trigger('select');
        }
        else {
          this.$element.trigger('focus');
        }
      }
      // Cloudflare Turnstile support. Resolves issue where turnstyle does
      // not load within a modal.
      if (typeof turnstile !== 'undefined') {
        if (this.$element.find('.cf-turnstile').length) {
          turnstile.reset();
        }
      }
    });
  }

  protected opened(param?:any) {
    this.bindElement();
    this.state = this.states.OPENED;
    this.$element.trigger(this.states.OPENED);
    this.event('opened').trigger(this);
    this.callCallback('onOpened');
    this.$element.addClass('isOpen');

    const $trigger = this.getTriggerElement();
    if ($trigger) {
      $trigger.addClass('exo-modal-opened');
    }

    Drupal.Exo.$document.on('keydown.' + this.name, e => {
      switch (e.which) {
        case 27: // escape
          this.close();
          break;
      }
    });
    // Support drimage module.
    if (typeof Drupal.drimage !== 'undefined') {
      setTimeout(() => {
        Drupal.drimage.init(this.$element.get(0));
      });
    }
  }

  public openNav(param?:any) {
    const groupModals = Drupal.ExoModal.getGrouped(this.get('group'));
    if (groupModals.count() > 1) {
      const prevModal = groupModals.getPrev(this.getId(), this.get('loop'));
      const nextModal = groupModals.getNext(this.getId(), this.get('loop'));
      var previousModal = null;

      if (typeof param == 'object') {
        if (param.previousModal !== undefined) {
          previousModal = param.previousModal;
        }
      }

      if (this.get('appendToNavigate') === false) {
        this.$navigate.appendTo('body');
      } else {
        if (this.get('appendToClosest') === true) {
          this.$navigate.appendTo(this.$element.closest(this.get('appendToNavigate')));
        }
        else {
          this.$navigate.appendTo(this.get('appendToNavigate'));
        }
        // If modal is within eXo content, we need to offset it so that it
        // accounts for displacement.
        if (this.$navigate.closest('.exo-content').length) {
          this.$navigate.css({
            top: displace.offsets.top,
            bottom: displace.offsets.bottom,
            left: displace.offsets.left,
            right: displace.offsets.right,
          });
        }
      }

      if (!previousModal) {
        this.$navigate.addClass('fadeIn');
      }

      if (this.get('navigateCaption') === true) {
        if (this.get('left') !== null && this.get('left') !== false) {
          this.$navigate.find('.' + this.name + '-navigate-caption').css('right', '10px').show();
        }
        else {
          this.$navigate.find('.' + this.name + '-navigate-caption').css('left', '10px').show();
        }
      }

      var modalWidth = this.$element.outerWidth();
      if (this.get('navigateArrows') !== false) {
        if (this.get('navigateArrows') === 'closeScreenEdge') {
          this.$navigate.find('.' + this.name + '-navigate-prev').css('left', 0).show();
          this.$navigate.find('.' + this.name + '-navigate-next').css('right', 0).show();
        } else {
          this.$navigate.find('.' + this.name + '-navigate-prev').css('margin-left', -((modalWidth / 2) + 84)).show();
          this.$navigate.find('.' + this.name + '-navigate-next').css('margin-right', -((modalWidth / 2) + 84)).show();
        }
      } else {
        this.$navigate.find('.' + this.name + '-navigate-prev').hide();
        this.$navigate.find('.' + this.name + '-navigate-next').hide();
      }

      if (!prevModal) {
        this.$navigate.find('.' + this.name + '-navigate-prev').hide();
      }
      if (!nextModal) {
        this.$navigate.find('.' + this.name + '-navigate-next').hide();
      }
    }
  }

  public close(param?:any) {
    if (this.state == this.states.OPENED || this.state == this.states.OPENING) {
      Drupal.Exo.$document.off('keydown.' + this.name);

      if (this.get('autoOpen')) {
        // When closed, we set dataOriginal to an empty object. This allows
        // modals opened via AJAX to be processed again as needed.
        this.dataOriginal = {};
      }

      this.state = this.states.CLOSING;
      this.$element.trigger(this.states.CLOSING);
      this.$element.attr('aria-hidden', 'true');

      if (!this.$element.closest('#exo-modals').length) {
        $('.exo-fixed-float:not(.exo-fixed-not-invisible)').removeClass('exo-fixed-invisible');
      }

      clearTimeout(this.timer);
      clearTimeout(this.timerTimeout);
      clearInterval(this.timerInterval);

      this.event('closing').trigger(this);
      this.callCallback('onClosing');

      let transitionOut = this.get('transitionOut');
      let transitionOutOverlay = this.get('transitionOutOverlay');
      if (this.get('transitionOutTemp') !== null) {
        transitionOut = this.get('transitionOutTemp');
        this.set('transitionOutTemp', null);
      }

      if (typeof param == 'object') {
        if (param.transition !== undefined || param.transitionOut !== undefined) {
          transitionOut = param.transition || param.transitionOut;
        }
      }

      if ((transitionOut === false || transitionOut === '') || Drupal.Exo.animationEvent === undefined) {
        this.$element.hide();
        this.$overlay.remove();
        this.$navigate.remove();
        this.closed();
      } else {
        this.$element.attr('class', [
          this.class,
          this.name,
          transitionOut,
          this.isFullscreen === true ? 'isFullscreen' : '',
          this.isCompressed === true ? 'isCompressed' : '',
          this.isSetHeight ? 'isSetHeight' : '',
          this.isTall === true ? 'isTall' : '',
          this.get('rtl') ? this.name + '-rtl' : ''
        ].join(' '));

        if (transitionOutOverlay) {
          this.$overlay.attr('class', this.name + '-overlay ' + transitionOutOverlay);
        }
        if (this.get('class') !== '') {
          this.$overlay.addClass(this.get('class') + '-overlay');
        }
        if (this.get('navigateArrows') !== false && !Drupal.Exo.isMobile()) {
          this.$navigate.attr('class', this.name + '-navigate fadeOut');
        }

        this.$element.on(Drupal.Exo.animationEvent + '.exo.modal', e => {
          if (e.target === e.currentTarget) {
            this.$element.off(Drupal.Exo.animationEvent + '.exo.modal');
            if (this.$element.hasClass(transitionOut)) {
              this.$element.removeClass(transitionOut + ' transitionOut').hide();
            }
            if (this.$overlay.hasClass(transitionOutOverlay)) {
              this.$overlay.removeClass(transitionOutOverlay).remove();
            }
            this.$navigate.removeClass('fadeOut').remove();
            this.closed();
          }
        });
      }
    }
  }

  protected closed(param?:any) {
    this.toggleContentSelector();
    this.state = this.states.CLOSED;
    this.$element.trigger(this.states.CLOSED);

    if (this.get('iframe') === true) {
      this.$element.find('.' + this.name + '-iframe').attr('src', '');
    }

    if (this.get('bodyOverflow') || Drupal.Exo.isMobile()) {
      if (Drupal.ExoModal.getVisible().count() === 0) {
        Drupal.Exo.unlockOverflow(this.$wrap);
      }
    }

    this.event('closed').trigger(this);
    window.dispatchEvent(new Event('dialog:afterclose'));
    this.callCallback('onClosed');
    this.$element.removeClass('isOpen');

    const $trigger = this.getTriggerElement();
    if ($trigger) {
      $trigger.removeClass('exo-modal-opened');
    }

    if (this.get('restoreDefaultContent') === true) {
      this.$content.html(this.content);
    }

    if ($('.' + this.name + ':visible').length === 0) {
      $('html').removeClass(this.name + '-isAttached');
    }

    if (this.get('contentAjax')) {
      this.$element.insertAfter(this.$contentAjaxPlaceholder);
      this.rebuildContent();
    }
    if (this.get('destroyOnClose')) {
      this.destroy(param);
    }
  }

  protected destroy(param?:any) {
    this.debug('log', 'Destroy', '[' + this.getId() + ']');

    var e = $.Event('destroy');
    this.$element.trigger(e);

    Drupal.Exo.$document.off('keydown.' + this.name);

    clearTimeout(this.timer);
    clearTimeout(this.timerTimeout);

    if (this.get('iframe') === true) {
      this.$element.find('.' + this.name + '-iframe').remove();
    }
    Drupal.detachBehaviors(this.$element.get(0), drupalSettings);
    this.$element.html(this.$content.html());

    this.$element.off('click', '[data-' + this.name + '-close]');
    this.$element.off('click', '[data-' + this.name + '-fullscreen]');

    this.$element
      .off('.' + this.name)
      .removeData(this.name)
      .attr('style', '')
      .remove();

    if (this.$contentAjaxPlaceholder) {
      this.$contentAjaxPlaceholder.remove();
    }
    this.$contentAjaxPlaceholder = null;
    this.contentAjaxLoaded = false;
    this.$overlay.remove();
    this.$navigate.remove();
    this.$element.trigger(this.states.DESTROYED);
    this.$element = null;
    this.autoOpenProcessed = false;
    this.state = this.states.CLOSED;
    this.built = false;
  }

  protected bindElement() {
    this.bindClose();
    // Expand when button pressed.
    this.$element.off('click.exo.modal', '[data-' + this.name + '-fullscreen]')
      .on('click.exo.modal', '[data-' + this.name + '-fullscreen]', e => {
        e.preventDefault();
        if (this.isFullscreen === true) {
          this.isFullscreen = false;
          this.$element.addClass('exo-no-transitions').removeClass('isFullscreen');
          setTimeout(() => {
            this.$element.removeClass('exo-no-transitions');
          }, 300);
        } else {
          this.isFullscreen = true;
          this.$element.addClass('exo-no-transitions isFullscreen');
          setTimeout(() => {
            this.$element.removeClass('exo-no-transitions');
          }, 300);
        }
        Drupal.Exo.checkElementPosition();
        this.$element.trigger('fullscreen', this);
        this.callCallback('onFullscreen');
      });

    // Next modal.
    this.$navigate.off('click.exo.modal', '.' + this.name + '-navigate-next')
      .on('click.exo.modal', '.' + this.name + '-navigate-next', e => {
        e.preventDefault();
        this.next(e);
      });
    this.$element.off('click.exo.modal', '[data-' + this.name + '-next]')
      .on('click.exo.modal', '[data-' + this.name + '-next]', e => {
        e.preventDefault();
        this.next(e);
      });

    // Previous modal.
    this.$navigate.off('click.exo.modal', '.' + this.name + '-navigate-prev')
      .on('click.exo.modal', '.' + this.name + '-navigate-prev', e => {
        e.preventDefault();
        this.prev(e);
      });
    this.$element.off('click.exo.modal', '[data-' + this.name + '-prev]')
      .on('click.exo.modal', '[data-' + this.name + '-prev]', e => {
        e.preventDefault();
        this.prev(e);
      });
  }

  protected bindClose() {
    // Close when button pressed.
    this.$element.find('[data-' + this.name + '-close]').off('click.exo.modal').on('click.exo.modal', e => {
      e.preventDefault();
      var transition = $(e.currentTarget).attr('data-' + this.name + '-transitionOut');

      if (transition !== undefined) {
        this.close({
          transition: transition
        });
      } else {
        this.close();
      }
    })
    .attr('tabindex', 0)
    .attr('aria-label', 'Close modal');
  }

  protected startProgress(param?:any) {
    this.isPaused = false;
    clearTimeout(this.timerTimeout);
    clearInterval(this.timerInterval);

    if (this.get('timeoutProgressbar') === true) {

      this.progressBar = {
        hideEta: null,
        maxHideTime: null,
        currentTime: new Date().getTime(),
        el: this.$element.find('.' + this.name + '-progressbar > div'),
        updateProgress: () => {
          if (!this.isPaused) {
            this.progressBar.currentTime = this.progressBar.currentTime + 10;
            var percentage = ((this.progressBar.hideEta - (this.progressBar.currentTime)) / this.progressBar.maxHideTime) * 100;
            this.progressBar.el.width(percentage + '%');
            if (percentage < 0) {
              this.close();
            }
          }
        }
      };
      if (param > 0) {
        this.progressBar.maxHideTime = parseFloat(param);
        this.progressBar.hideEta = new Date()
          .getTime() + this.progressBar.maxHideTime;
        this.timerInterval = setInterval(this.progressBar.updateProgress, 10);
      }

    } else {
      this.timerTimeout = setTimeout(() => {
        this.close();
      }, this.get('timeout'));
    }
  }

  protected pauseProgress() {
    this.isPaused = true;
  }

  protected resumeProgress() {
    this.isPaused = false;
  }

  protected resetProgress(param?:any) {
    clearTimeout(this.timerTimeout);
    clearInterval(this.timerInterval);
    this.progressBar = {};
    this.$element.find('.' + this.name + '-progressbar > div').width('100%');
  }

  protected recalcLayout() {
    if (this.state == this.states.OPENED || this.state == this.states.OPENING) {
      let windowHeight:number = Drupal.Exo.$window.height() - (displace.offsets.top + displace.offsets.bottom);
      const windowWidth:number = Drupal.Exo.$window.width() - (displace.offsets.left + displace.offsets.right);
      let modalHeight:number = this.$element.outerHeight();
      const modalWidth:number = this.$element.outerWidth();
      const contentHeight:number = this.$content[0].scrollHeight;
      const sectionHeaderHeight:number = this.$sectionHeader.outerHeight() || 0;
      const sectionFooterHeight:number = this.$sectionFooter.outerHeight() || 0;
      const scrollTop:number = this.$wrap.scrollTop();
      const paddingV = this.$content.innerHeight() - this.$content.height();
      const paddingH = this.$content.innerWidth() - this.$content.width();
      let borderSize:number = 0;
      let allowScroll:boolean = false;

      if (Drupal.Exo.isIE()) {
        if (modalWidth >= Drupal.Exo.$window.width() || this.isFullscreen === true) {
          this.$element.css({
            left: '0',
            marginLeft: ''
          });
        }
      }

      if (this.get('borderBottom') === true && this.get('title') !== '' && this.get('hideTitle') === false) {
        borderSize = 3;
      }

      this.headerHeight = 0;
      if (this.$element.find('.' + this.name + '-header').length && this.$element.find('.' + this.name + '-header').is(':visible')) {
        this.headerHeight = this.$element.find('.' + this.name + '-header').innerHeight();
      }

      this.footerHeight = 0;
      if (this.$element.find('.' + this.name + '-footer').length && this.$element.find('.' + this.name + '-footer').is(':visible')) {
        this.footerHeight = this.$element.find('.' + this.name + '-footer').innerHeight();
      }

      const endHeight = this.headerHeight + this.footerHeight + sectionHeaderHeight + sectionFooterHeight;
      const wrapperHeight:number = this.$element.outerHeight() - endHeight;
      let outerHeight:number = 0;
      if (this.isSetHeight) {
        // If we have a set height, use modal height.
        outerHeight =  modalHeight;
        allowScroll = true;
      }
      else {
        outerHeight = contentHeight + endHeight;
      }

      if (this.$element.find('.' + this.name + '-loader').length) {
        this.$element.find('.' + this.name + '-loader').css('top', this.headerHeight);
      }

      if (modalHeight !== this.modalHeight) {
        this.modalHeight = modalHeight;
        this.callCallback('onResize');
      }

      if (this.get('iframe') === true) {
        if (this.get('iframeHeight') !== null && this.get('iframeWidth') !== null && this.get('iframeResponsive') === true) {
          if (windowHeight <= this.$element.outerHeight() || this.isFullscreen === true) {
            let newHeight = windowHeight;
            let percentOfChange = newHeight / this.$element.outerHeight();
            this.$element.css('max-width', (this.$element.width() * percentOfChange));
          }
          else {
            this.recalcSize();
          }
        }
        else {
          // If the height of the window is smaller than the modal with iframe
          if (windowHeight < (this.get('iframeHeight') + endHeight + borderSize) || this.isFullscreen === true) {
            this.$element.find('.' + this.name + '-iframe').css('height', Math.round(windowHeight - (endHeight + borderSize + paddingV)));
          } else {
            this.$element.find('.' + this.name + '-iframe').css('height', this.get('iframeHeight'));
          }
        }
      }
      else {
        if (this.$element.find('.' + this.name + '-expand:visible').length === 1) {
          this.$element.find('.' + this.name + '-expand')
            .height(windowHeight - (endHeight + borderSize + paddingV))
            .width(windowWidth - paddingH);
          this.$element.css('max-width', '');
        }
        else {
          this.recalcSize();
        }
      }

      if (modalHeight == windowHeight) {
        this.$element.addClass('isAttached');
      } else {
        this.$element.removeClass('isAttached');
      }

      if (this.isFullscreen === false && this.$element.width() >= Drupal.Exo.$window.width()) {
        this.$element.find('.' + this.name + '-button-fullscreen').hide();
      } else {
        this.$element.find('.' + this.name + '-button-fullscreen').show();
      }
      this.recalcButtons();

      if (this.isFullscreen === false) {
        windowHeight = windowHeight - (Drupal.Exo.getMeasurementValue(this.get('top')) || 0) - (Drupal.Exo.getMeasurementValue(this.get('bottom')) || 0);
      }
      // If the modal is larger than the height of the window.
      if (outerHeight > windowHeight) {
        if (this.get('top') > 0 && this.get('bottom') === null && contentHeight < Drupal.Exo.$window.height()) {
          this.$element.addClass('isAttachedBottom');
        }
        if (this.get('bottom') > 0 && this.get('top') === null && contentHeight < Drupal.Exo.$window.height()) {
          this.$element.addClass('isAttachedTop');
        }
        if ($('.' + this.name + ':visible').length === 1) {
          $('html').addClass(this.name + '-isAttached');
        }
        this.$element.css('height', windowHeight);
      } else {
        if (this.isTall === true) {
          this.$element.css('height', windowHeight);
        }
        else if (this.get('top') && this.get('bottom')) {
          this.$element.css('height', windowHeight);
        }
        else if (!this.isSetHeight) {
          this.$element.css('height', contentHeight + (endHeight + borderSize));
        }
        this.$element.removeClass('isAttachedTop isAttachedBottom');
        if ($('.' + this.name + ':visible').length === 1) {
          $('html').removeClass(this.name + '-isAttached');
        }
      }

      (applyScroll => {
        if (contentHeight > wrapperHeight && (allowScroll || outerHeight > windowHeight)) {
          this.$element.addClass('hasScroll');
          this.$wrap.css('height', modalHeight - (endHeight + borderSize));
        } else {
          this.$element.removeClass('hasScroll');
          this.$wrap.css('height', '');
        }
      })();

      (applyShadow => {
        if (wrapperHeight + scrollTop < (contentHeight - 30)) {
          this.$element.addClass('hasShadow');
        } else {
          this.$element.removeClass('hasShadow');
        }
      })();
    }
  }

  protected recalcButtons() {
    var widthButtons = this.$header.find('.' + this.name + '-header-buttons').innerWidth() + 10;
    if (this.get('rtl') === true) {
      this.$header.css('padding-left', widthButtons);
    } else {
      this.$header.css('padding-right', widthButtons);
    }
  }

  public next(event:JQuery.Event) {
    const modals = Drupal.ExoModal.getGrouped(this.get('group'));
    const modal = modals.getNext(this.getId(), this.get('loop'));

    if (modal) {
      const transitionOut = this.get('transitionOut') === this.defaults.transitionOut ? 'fadeOutLeft' : this.get('transitionOut');
      const transitionIn = modal.get('transitionIn') === this.defaults.transitionIn ? 'fadeInRight' : modal.get('transitionIn');
      this.set('transitionOutTemp', transitionOut);
      modal.open({
        transition: transitionIn,
        previousModal: this,
      });
    }
  }

  public prev(event:JQuery.Event) {
    const modals = Drupal.ExoModal.getGrouped(this.get('group'));
    const modal = modals.getPrev(this.getId(), this.get('loop'));

    if (modal) {
      const transitionOut = this.get('transitionOut') === this.defaults.transitionOut ? 'fadeOutRight' : this.get('transitionOut');
      const transitionIn = modal.get('transitionIn') === this.defaults.transitionIn ? 'fadeInLeft' : modal.get('transitionIn');
      this.set('transitionOutTemp', transitionOut);
      modal.open({
        transition: transitionIn,
        previousModal: this,
      });
    }
  }

  protected bindTrigger() {
    const $trigger = this.getTriggerElement();
    if ($trigger.length) {
      $trigger.off('click.exo.modal.' + this.getId()).on('click.exo.modal.' + this.getId(), e => {
        e.preventDefault();
        this.toggle();
      }).off('keydown.exo.modal.' + this.getId()).on('keydown.exo.modal.' + this.getId(), e => {
        switch (e.which) {
          case 13: // enter
          case 32: // space
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
            break;
        }
      });
    }
  }

  public addDestination(url:string):string {
    const parts = url.split('?');
    let currentDestination = null;
    if (parts[1]) {
      currentDestination = new URLSearchParams('?' + parts[1]).get('destination');
    }
    if (!currentDestination) {
      let key = 'destination';
      let value = window.location.pathname;
      key = encodeURI(key);
      value = encodeURI(value);
      var isQuestionMarkPresent = url && url.indexOf('?') !== -1,
        separator = isQuestionMarkPresent ? '&' : '?';
      url += separator + key + '=' + value;
    }
    return url;
  }

  public isOpen():boolean {
    return this.state === this.states.OPENED || this.state === this.states.OPENING;
  }

  public getState():string {
    return this.state;
  }

  public getSelector():string {
    return this.getId().replace(/_/g, '-');
  }

  public getSelectorAsId():string {
    return '#' + this.getSelector();
  }

  public getTriggerElement():JQuery {
    this.$trigger = $('.' + this.getSelector() + '-trigger');
    return this.$trigger;
  }

  public getElement():JQuery {
    const $elements = $('.' + this.getSelector());
    this.$element = $elements.first();
    // Select with a class as multiple may exist due to caching. We remove any
    // duplicates.
    if ($elements.length > 1) {
      $elements.each(function (index, element) {
        if (index !== 0) {
          $(element).remove();
        }
      });
    }
    return this.$element;
  }

  public getOverlay():JQuery {
    return this.$overlay;
  }

}
