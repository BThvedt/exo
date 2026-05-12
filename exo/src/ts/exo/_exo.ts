class Exo {
  protected label:string = 'Exo';
  protected doDebug:boolean = false;
  public $window:JQuery<Window>;
  public $document:JQuery<Document>;
  public $body:JQuery;
  public $exoBody:JQuery;
  public $exoCanvas:JQuery;
  public $exoContent:JQuery;
  public $exoStyle:JQuery;
  protected $exoShadow:JQuery;
  protected $elementPositions:JQuery;
  protected resizeCallbacks:ExoCallbacks = {};
  protected resizeWidth:number;
  protected exoShadowTimeout:ReturnType<typeof setTimeout>;
  protected initPromises:Array<Promise<boolean>> = [];
  protected revealPromises:Array<Promise<boolean>> = [];
  protected initialized:boolean = false;
  protected shadowUsage:number = 0;
  protected shadowCallbacks:Array<Function> = [];
  protected scrollThrottle:number = 99;
  protected observer:{[s: string]: IntersectionObserver} = {};
  protected observerThreshold:Array<number> = [0, 1];
  protected styleProps = {};
  public observables = [];
  private readonly events = {
    init: new ExoEvent<void>(),
    ready: new ExoEvent<void>(),
    reveal: new ExoEvent<void>(),
    finished: new ExoEvent<void>(),
    breakpoint: new ExoEvent<any>(),
  };

  public speed:number = 300; // Should be the same as speed set in _variables.scss.
  public animationEvent:string;
  public transitionEvent:string;
  public breakpoint:any = {
    min: null,
    max: null,
    name: null,
  };

  constructor() {
    this.$window = $(window);
    this.$document = $(document);
    this.$body = $('body');
    this.$exoBody = $('#exo-body');
    this.$exoCanvas = $('#exo-canvas');
    this.$exoContent = $('#exo-content');
    this.$exoShadow = $('#exo-shadow');
    this.$exoStyle = $('<style id="exo-style" type="text/css" />').appendTo('head');
    this.animationEvent = this.whichAnimationEvent();
    this.transitionEvent = this.whichTransitionEvent();
    this.refreshBreakpoint();

    this.$exoShadow.on('click.exoShowShadow', e => {
      const callback = this.shadowCallbacks[this.shadowCallbacks.length - 1];
      if (callback) {
        callback(e);
      }
    });

    if (this.isFirefox()) {
      this.$body.addClass('is-firefox');
    }
    else if (this.isIE()) {
      this.$body.addClass('is-ie');
    }

    if (this.isTouch()) {
      this.$body.addClass('has-touch');
    }
    else {
      this.$body.addClass('no-touch');
    }
  }

  public init() {
    this.debug('log', this.label, 'Init');
    // Due to deferred loading of CSS, we want to wait until ready to enable
    // animations.
    // We use a timeout so other modules that act on the init event have time
    // to register their promises.
    setTimeout(() => {
      this.doInit();
    });
  }

  protected doInit() {
    // All dependencies have been met. We are not yet ready to reveal the page
    // content but any modules that need to act before content is revealed can
    // do so with this event.
    this.event('init').trigger();

    // Trigger resize event.
    this.onResize();

    // Ready will be fired after 3 seconds no matter what.
    const initTimer = setTimeout(() => {
      this.ready();
    }, 3000);

    // Once all the dependencies have finished, unleashed the init.
    this.debug('log', this.label, 'Init Promises', this.initPromises);
    Promise.all(this.initPromises).then(values => {
      clearTimeout(initTimer);
      setTimeout(() => {
        this.ready();
      });
    });

    // Resize on once all images have been loaded.
    this.$exoBody.imagesLoaded(() => {
      if (this.initialized === true) {
        this.displaceContent();
      }
      setTimeout(() => {
        if (typeof Drupal.drimage !== 'undefined') {
          Drupal.drimage.init();
        }
      });
    });

    const resizeThrottle = _.throttle(() => {
      this.onResize();
    }, 99);
    this.$window.on('resize.exo', (e) => {
      this.refreshBreakpoint();
      resizeThrottle();
    });
  }

  // This runs as a standard attach.
  public attach(context) {
    this.event('init').trigger();
    this.event('ready').trigger();
    this.$document.trigger('exoReady');
    this.event('reveal').trigger();
    this.$document.trigger('exoReveal');
    this.checkElementPosition();
    this.bindAnchors(context);
  }

  protected ready() {
    this.debug('log', this.label, 'Ready');
    this.initialized = true;
    this.$body.addClass('exo-ready');
    this.event('ready').trigger();
    this.$document.trigger('exoReady');
    this.displaceContent();
    this.resizeContent();

    // Will fire finished once when the page is revealed.
    let finishedTimer;
    Drupal.Exo.event('reveal').on('exo.reveal', () => {
      clearTimeout(finishedTimer);
      finishedTimer = setTimeout(() => {
        Drupal.Exo.event('finished').trigger();
        Drupal.Exo.event('reveal').off('exo.reveal');
      }, 300);
    });

    // Once all the dependencies have finished, unleashed the init.
    this.debug('log', this.label, 'Reveal Promises', this.revealPromises);
    Promise.all(this.revealPromises).then(values => {
      this.debug('log', this.label, 'Reveal');
      this.event('reveal').trigger();
      this.$document.trigger('exoReveal');
    });
  }

  /**
   * Check if eXo has been initialized.
   */
  public isInitialized():boolean {
    return this.initialized;
  }

  /**
   * Add a dependency to eXo initialization.
   *
   * @param promise
   */
  public addInitWait(promise:Promise<boolean>) {
    this.debug('log', this.label, 'Init Wait Added');
    this.initPromises.push(promise);
  }

  /**
   * Add a dependency to eXo reveal.
   *
   * @param promise
   */
  public addRevealWait(promise:Promise<boolean>) {
    this.debug('log', this.label, 'Reveal Wait Added');
    this.revealPromises.push(promise);
  }

  /**
   * Return the #exo-body jQuery element.
   */
  public getBodyElement():JQuery {
    return this.$exoBody;
  }

  /**
   * On resize callback.
   */
  public onResize() {
    this.debug('log', this.label, 'onResize');
    Drupal.ExoDisplace.calculate();
    if (this.initialized === true) {
      // Mobile browsers show/hide toolbar which fires resize. We watch for
      // width changes before firing this.
      if (this.resizeWidth !== window.innerWidth) {
        this.displaceContent();
        this.resizeContent();
        for (const key in this.resizeCallbacks) {
          if (this.resizeCallbacks.hasOwnProperty(key)) {
            this.resizeCallbacks[key]();
          }
        }
      }
    }
    this.resizeWidth = window.innerWidth;
    this.checkElementPosition();
  }

  public addOnResize(id:string, callback:Function) {
    this.resizeCallbacks[id] = callback;
  }

  public removeOnResize(id:string) {
    if (typeof this.resizeCallbacks[id] !== 'undefined') {
      delete this.resizeCallbacks[id];
    }
  }

  public addStyleProp(property:string, value:string) {
    this.styleProps[property] = value;
  }

  public removeStyleProp(property:string) {
    if (typeof this.styleProps[property] !== 'undefined') {
      delete this.styleProps[property];
    }
  }

  public updateStyle() {
    let style = ':root {';
    for (const key in this.styleProps) {
      if (this.styleProps.hasOwnProperty(key)) {
        style += '--' + key + ': ' + this.styleProps[key] + ';';
      }
    }
    style += '}';
    this.$exoStyle.html(style);
  }

  public getOffsetTop() {
    let top = displace.offsets.top;
    const $fixed = $('.header.exo-fixed-element');
    if ($fixed.length) {
      top += $fixed.outerHeight();
    }
    return top;
  }

  protected bindAnchors(context) {
    // core/once requires Element targets; the previous code marked the
    // global `window` jQuery wrapper, which jQuery.once allowed but
    // core/once does not. Mark <html> instead — equally globally unique
    // since there is exactly one documentElement per page, and the
    // popstate handler still binds against window via $(window).on.
    once('exo.hash.window', document.documentElement).forEach(() => {
      Drupal.Exo.$window.on('popstate.exo', e => {
        let hash = location.hash;
        if (hash) {
          hash = hash.substring(1);
          const $anchor = $('a[name="' + hash + '"]');
          if ($anchor.length) {
            $('html, body').animate({
              scrollTop: $anchor.offset().top,
            }, 500);
          }
        }
      });
    });

    once('exo.hash', 'a[href^="#"]', context).forEach((element:HTMLElement) => {
      const $link = $(element);
      const hash = $link.attr('href').substring(1);
      if (hash) {
        const $anchor = $('a[name="' + hash + '"]');
        if ($anchor.length) {
          $link.on('click', e => {
            e.preventDefault();
            $('html, body').animate({
              scrollTop: $anchor.offset().top,
            }, 500);
            if(history.pushState) {
              history.pushState({hash: hash}, null, '#' + hash);
            }
            else {
              location.hash = hash;
            }
          });
        }
      }
    });
  }

  /**
   * Displace content area.
   *
   * @see exo.html.twig.
   */
  public displaceContent(offsets?) {
    offsets = offsets || displace.offsets;
    this.debug('log', this.label, 'displaceContent', offsets);
    this.$exoBody.css({
      paddingTop: offsets.top,
      paddingBottom: offsets.bottom,
      paddingLeft: offsets.left,
      paddingRight: offsets.right,
    });
    this.addStyleProp('displace-top', offsets.top + 'px');
    this.addStyleProp('displace-bottom', offsets.bottom + 'px');
    this.addStyleProp('displace-left', offsets.left + 'px');
    this.addStyleProp('displace-right', offsets.right + 'px');
    this.updateStyle();
    ['top', 'right', 'bottom', 'left'].forEach(side => {
      if (offsets[side]) {
        this.$exoBody.find('.exo-displace-' + side).css('top', offsets[side] + 'px');
        this.$exoBody.find('.exo-displace-padding-' + side).css('padding-' + side, offsets[side] + 'px');
        this.$exoBody.find('.exo-displace-margin-' + side).css('margin-' + side, offsets[side] + 'px');
      }
    });
    if (window.localStorage) {
      window.localStorage.setItem('exoBodySize', JSON.stringify(offsets));
    }
    if (typeof drupalSettings.gin !== 'undefined' && drupalSettings.path.currentPathIsAdmin === true) {
      // Support Gin theme.
      $('.layout-region-node-secondary').css({
        top: offsets.top + 'px',
        height: 'calc(100% - ' + offsets.top + 'px)',
      });
      $('.layout-region-node-actions').css({
        top: offsets.top + 'px',
      });
      const $regionSticky = $('.region-sticky');
      $regionSticky.css({
        top: offsets.top + 'px',
      });
      $('.sticky-shadow').css({
        position: 'absolute',
        top: '100%',
        left: 0,
        right: 0,
      }).appendTo($regionSticky);
    }
  }

  /**
   * Resize content area.
   */
  public resizeContent() {
    const height = this.$window.height() - (parseInt(this.$exoBody.css('paddingTop')) + parseInt(this.$exoBody.css('paddingBottom')));
    this.debug('log', this.label, 'resizeContent', height);
    this.$exoContent.css('min-height', height);
    if (window.localStorage) {
      window.localStorage.setItem('exoContentHeight', String(this.$exoContent.height()));
    }
  }

  /**
   * Show content shadow.
   *
   * @return {Promise<void>}
   */
  public showShadow(options?:ExoShadowOptionsInterface):Promise<void> {
    options = _.extend({
      opacity: .8,
      onClick: null
    }, options);
    this.shadowUsage++;
    if (options.onClick) {
      this.shadowCallbacks.push(options.onClick);
    }
    return new Promise<void>((resolve, reject) => {
      clearTimeout(this.exoShadowTimeout);
      this.$exoShadow.addClass('active');
      this.exoShadowTimeout = setTimeout(() => {
        this.$exoShadow.addClass('animate').css('opacity', options.opacity);
        resolve();
      }, 20);
    });
  }

  /**
   * Hide content shadow.
   *
   * @return {Promise<void>}
   */
  public hideShadow():Promise<void> {
    return new Promise<void>((resolve, reject) => {
      this.shadowUsage--;
      this.shadowCallbacks.pop();
      if (this.shadowUsage <= 0) {
        this.shadowUsage = 0;
        this.shadowCallbacks = [];
        clearTimeout(this.exoShadowTimeout);
        this.$exoShadow.removeClass('animate').css('opacity', 0);
        this.exoShadowTimeout = setTimeout(() => {
          this.$exoShadow.removeClass('active');
          resolve();
        }, this.speed);
      }
      else {
        resolve();
      }
    });
  }

  public setScrollThrottle(throttle:number) {
    this.scrollThrottle = throttle;
  }

  public setObserverThreshold(threshold:Array<number>) {
    this.observerThreshold = threshold;
  }

  public observeElement($elements:JQuery, inViewportCallback?:Function, outViewportCallback?:Function, observedCallback?:Function, observerId?:string, observerOptions?:IntersectionObserverInit) {
    return new Promise<void>((resolve, reject) => {
      observerId = observerId || 'exo';
      if (typeof this.observer[observerId] === 'undefined') {
        observerOptions = observerOptions || {
          threshold: this.observerThreshold
        };
        this.observer[observerId] = new IntersectionObserver(this.observed, observerOptions);
      }
      $elements.each((index, element) => {
        this.observables.push({
          inViewportCallback: inViewportCallback || null,
          outViewportCallback: outViewportCallback || null,
          observedCallback: observedCallback || null,
        });
        element.dataset.exoActive = 'false';
        element.dataset.exoObserverId = observerId;
        element.dataset.exoObservableId = (this.observables.length - 1).toString();
        this.observer[observerId].observe(element);
      });
      resolve();
    });
  }

  protected observed(entries) {
    entries.forEach((entry) => {
      const element = entry.target;
      const $element = $(element);
      const ratio = entry.intersectionRatio;
      const boundingRect = entry.boundingClientRect;
      const offsetTop = Math.round(window.scrollY + displace.offsets.top);
      const wrapperHeight = Math.round(window.innerHeight - displace.offsets.top - displace.offsets.bottom);
      const offsetBottom = offsetTop + wrapperHeight;
      const active = element.dataset.exoActive === 'true';
      const data = Drupal.Exo.observables[parseInt(element.dataset.exoObservableId)];

      if (typeof data.observedCallback === 'function') {
        data.observedCallback($element, offsetTop, offsetBottom, boundingRect, entry);
      }
      if (ratio === 0) {
        if (active) {
          element.dataset.exoActive = 'false';
          if (typeof data.outViewportCallback === 'function') {
            data.outViewportCallback($element, offsetTop, offsetBottom, boundingRect, entry);
          }
        }
      } else {
        if (!active) {
          element.dataset.exoActive = 'true';
          if (typeof data.inViewportCallback === 'function') {
            data.inViewportCallback($element, offsetTop, offsetBottom, boundingRect, entry);
          }
        }
      }
    });
  }

  public trackElementPosition($element:HTMLElement|JQuery, inViewportCallback?:Function, outViewportCallback?:Function, scrollCallback?:Function, observedCallback?:Function) {
    return new Promise<void>((resolve, reject) => {
      if ($element instanceof HTMLElement) {
        $element = $($element);
      }
      this.untrackElementPosition($element);
      // core/once on raw DOM elements: returns only the elements that
      // weren't already marked. If any were freshly marked, bind the
      // observers — matches the previous .once(...).length gate.
      if (once('exo.track.position', $element.get()).length) {
        if (typeof inViewportCallback === 'function' || typeof outViewportCallback === 'function' || typeof observedCallback === 'function') {
          this.observeElement($element, inViewportCallback, outViewportCallback, observedCallback);
        }
        if (typeof scrollCallback) {
          this.trackElementScroll($element, scrollCallback);
        }
      }
      resolve();
    });
  }

  protected trackElementScroll($element:JQuery, scrollCallback?:Function) {
    if (!this.$elementPositions) {
      this.$elementPositions = $();
      this.$window.on('scroll.exo', _.throttle(e => this.checkElementPosition(), this.scrollThrottle));
    }
    $element.data('exoScrollCallback', scrollCallback);
    this.$elementPositions = this.$elementPositions.add($element);
    this.checkElementPosition();
  }

  public untrackElementPosition($element:HTMLElement|JQuery) {
    if ($element instanceof HTMLElement) {
      $element = $($element);
    }
    // findOnce → once.filter (read marks without modifying them).
    // removeOnce → once.remove (strip the mark so a later trackElement-
    // Position call will mark the element again).
    const elements = $element.get();
    if (!once.filter('exo.track.position', elements).length) {
      return;
    }
    once.remove('exo.track.position', elements);
    if ($element[0].dataset.exoObservableId) {
      this.observer[$element[0].dataset.exoObserverId].unobserve($element[0]);
    }
    if (this.$elementPositions && this.$elementPositions.length) {
      this.$elementPositions = this.$elementPositions.not($element);
    }
  }

  public checkElementPosition() {
    if (typeof this.$elementPositions !== 'undefined' && this.$elementPositions.length) {
      const offsetTop = window.scrollY + displace.offsets.top;
      const wrapperHeight = window.innerHeight - displace.offsets.top - displace.offsets.bottom;
      const offsetBottom = offsetTop + wrapperHeight;
      this.$elementPositions.each((index, element) => {
        const $element = $(element);
        const scrollCallback = $element.data('exoScrollCallback');
        if (typeof scrollCallback === 'function') {
          scrollCallback($element, offsetTop, offsetBottom, element.getBoundingClientRect());
        }
      });
    }
  }

  public cleanElementPosition(context) {
    if (this.$elementPositions && this.$elementPositions.length) {
      this.$elementPositions.each((index, element) => {
        const $element = $(element);
        if ($element.closest(context).length) {
          this.$elementPositions = this.$elementPositions.not($element);
        }
      });
    }
  }

  /**
   * Refresh breakpoint information. See _global.scss.
   */
  public refreshBreakpoint() {
    const value:any = {};
    const property:string = String(window.getComputedStyle(document.querySelector('body'), ':before').getPropertyValue('content'));
    property.split('|').forEach(section => {
      const parts = section.replace('"', '').split(':');
      value[parts[0]] = parts[1];
    });
    if (value.min !== this.breakpoint.min) {
      this.breakpoint = value;
      this.event('breakpoint').trigger(value);
    }
  }

  public lockOverflow($element?:HTMLElement|JQuery, options?) {
    if ($element && bodyScrollLock) {
      if ($element instanceof HTMLElement) {
        $element = $($element);
      }
      bodyScrollLock.lock($element.get(0), options);
    }
    else {
      $('body').css('top', -(document.documentElement.scrollTop) + 'px');
      $('html').addClass('exo-lock-overflow');
    }
  }

  public unlockOverflow($element?:HTMLElement|JQuery) {
    if ($element && bodyScrollLock) {
      if ($element instanceof HTMLElement) {
        $element = $($element);
      }
      bodyScrollLock.unlock($element.get(0));
    }
    else {
      const scrollTop = parseInt($('body').css('top')) * -1;
      $('body').css('top', '');
      $('html').removeClass('exo-lock-overflow');
      if (scrollTop) {
        setTimeout(function () {
          window.scrollTo(0, scrollTop);
        });
      }
    }
  }

  /**
   * Remove measurement unit from string.
   */
  public getMeasurementValue(value:string) {
    let separators = /%|px|em|cm|vh|vw/;
    return parseInt(String(value).split(separators)[0]);
  }

  /**
   * Remove measurement unit from string.
   */
  public getMeasurementUnit(value:string) {
    return String(value).match(/[\d.\-\+]*\s*(.*)/)[1] || '';
  }

  /**
   * Convert PX to EM.
   */
  public getPxFromEm(em:string|number) {
    em = this.getMeasurementValue(String(em));
    return em * parseFloat(getComputedStyle(document.querySelector('body'))['font-size']);
  }

  /**
   * Determine if browser is IE version.
   */
  public isIE(version?:number):boolean {
    if(version === 9){
      return navigator.appVersion.indexOf('MSIE 9.') !== -1;
    } else {
      const userAgent = navigator.userAgent;
      return userAgent.indexOf('MSIE ') > -1 || userAgent.indexOf('Trident/') > -1;
    }
  }

  /**
   * Use breakpoints to determine mobile.
   */
  public isMobile():boolean {
    return this.breakpoint.name === 'small';
  }

  /**
   * Determine if browser is Firefox version.
   */
  public isFirefox():boolean {
    return navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
  }

  /**
   * Determine if browser is iOS.
   */
  public isIos():boolean {
    return (/iPhone|iPod/.test(navigator.userAgent)) && !window.MSStream;
  }

  /**
   * Determine if browser is iPadOS.
   */
  public isIpadOs():boolean {
    return (/iPad/.test(navigator.userAgent)) && !window.MSStream;
  }

  /**
   * Determine if browser is Desktop Safari.
   */
  public isSafari():boolean {
    return window.safari !== undefined && !this.isIos() && !this.isIpadOs();
  }

  /**
   * Determine if touch enabled device.
   */
  public isTouch():boolean {
    return 'ontouchstart' in document.documentElement;
  }

  /**
   * Determine the appropriate event for CSS3 animation end.
   */
  public whichAnimationEvent(){
    let transition;
    const el = document.createElement('fakeelement');

    var transitions = {
      'animation' :'animationend',
      'OAnimation' :'oAnimationEnd',
      'MozAnimation' :'animationend',
      'WebkitAnimation' :'webkitAnimationEnd'
    }

    for (transition in transitions){
      if (el.style[transition] !== undefined){
        return transitions[transition];
      }
    }
  }

  /**
   * Generate a unique id.
   *
   * @return {string}
   *   A unique id.
   */
  public guid() {
    function s4() {
      return Math.floor((1 + Math.random()) * 0x10000).toString(16).substring(1);
    }
    return s4() + s4() + '-' + s4() + '-' + s4() + '-' + s4() + '-' + s4() + s4() + s4();
  }

  /**
   * Determine the appropriate event for CSS3 transition end.
   */
  public whichTransitionEvent(){
    let transition;
    const el = document.createElement('fakeelement');

    var transitions = {
      'transition':'transitionend',
      'OTransition':'oTransitionEnd',
      'MozTransition':'transitionend',
      'WebkitTransition':'webkitTransitionEnd'
    }

    for (transition in transitions){
      if (el.style[transition] !== undefined){
        return transitions[transition];
      }
    }
  }

  /**
   * Get events for subscribing and triggering.
   */
  public event(type:string):ExoEvent<any> {
    if (typeof this.events[type] !== 'undefined') {
      return this.events[type].expose();
    }
    return null;
  }

  /**
   * Convert a string to a function if it exists within the global scope.
   */
  public stringToCallback(str:string):{object:object, function:string} {
    let callback:{object:object, function:string} = null;
    if (typeof str === 'string') {
      const parts:Array<string> = str.split('.');
      let scope = window;
      parts.forEach(value => {
        if (scope[value]) {
          if (typeof scope[value] === 'function' && typeof scope === 'object') {
            callback = {
              object: scope,
              function: value
            };
          }
          else {
            scope = scope[value];
          }
        }
      });
    }
    return callback;
  }

  public cleanData(data, defaults) {
    jQuery.each(data, (index, val) => {
      if (val == 'true') {
        data[index] = true;
      } else if (val == 'false') {
        data[index] = false;
      } else if ((val === '1' || val === 1) && typeof defaults[index] === 'boolean') {
        data[index] = true;
      } else if ((val === '0' || val === 0) && typeof defaults[index] === 'boolean') {
        data[index] = false;
      } else if (/^\d+$/.test(val)) {
        data[index] = parseInt(val);
      }
    });
    return data;
  }

  public toCamel(str:string) {
    return str.replace(/[-_]+([a-z])/g, function (g) { return g[1].toUpperCase(); });
  }

  public toSnake(str:string) {
    return str.replace( /([A-Z])/g, "_$1").toLowerCase();
  }

  public toDashed(str:string) {
    return str.replace( /([A-Z])/g, "-$1").toLowerCase();
  }

  /**
   * Log debug message.
   */
  public debug(type:string, label:string, ...args) {
    if (label === this.label && this.doDebug === false) {
      return;
    }
    switch (type) {
      case 'info':
        console.info('[eXo ' + label + ']', ...args); // eslint-disable-line no-console
        break;
      case 'warn':
        console.warn('[eXo ' + label + ']', ...args); // eslint-disable-line no-console
        break;
      case 'error':
        console.error('[eXo ' + label + ']', ...args); // eslint-disable-line no-console
        break;
      default:
        console.log('[eXo ' + label + ']', ...args); // eslint-disable-line no-console
        break;
    }
  }

}

Drupal.Exo = new Exo();
