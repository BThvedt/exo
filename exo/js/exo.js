"use strict";function _get(){return(_get="undefined"!=typeof Reflect&&Reflect.get?Reflect.get.bind():function(e,t,n){var i=_superPropBase(e,t);if(i)return(i=Object.getOwnPropertyDescriptor(i,t)).get?i.get.call(arguments.length<3?e:n):i.value}).apply(this,arguments)}function _superPropBase(e,t){for(;!Object.prototype.hasOwnProperty.call(e,t)&&null!==(e=_getPrototypeOf(e)););return e}function _inherits(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&_setPrototypeOf(e,t)}function _setPrototypeOf(e,t){return(_setPrototypeOf=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(e,t){return e.__proto__=t,e})(e,t)}function _createSuper(n){var i=_isNativeReflectConstruct();return function(){var e,t=_getPrototypeOf(n);return _possibleConstructorReturn(this,i?(e=_getPrototypeOf(this).constructor,Reflect.construct(t,arguments,e)):t.apply(this,arguments))}}function _possibleConstructorReturn(e,t){if(t&&("object"===_typeof(t)||"function"==typeof t))return t;if(void 0!==t)throw new TypeError("Derived constructors may only return object or undefined");return _assertThisInitialized(e)}function _assertThisInitialized(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}function _isNativeReflectConstruct(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],function(){})),!0}catch(e){return!1}}function _getPrototypeOf(e){return(_getPrototypeOf=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function _typeof(e){return(_typeof="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function _defineProperties(e,t){for(var n=0;n<t.length;n++){var i=t[n];i.enumerable=i.enumerable||!1,i.configurable=!0,"value"in i&&(i.writable=!0),Object.defineProperty(e,_toPropertyKey(i.key),i)}}function _createClass(e,t,n){return t&&_defineProperties(e.prototype,t),n&&_defineProperties(e,n),Object.defineProperty(e,"prototype",{writable:!1}),e}function _toPropertyKey(e){e=_toPrimitive(e,"string");return"symbol"===_typeof(e)?e:String(e)}function _toPrimitive(e,t){if("object"!==_typeof(e)||null===e)return e;var n=e[Symbol.toPrimitive];if(void 0===n)return("string"===t?String:Number)(e);n=n.call(e,t||"default");if("object"!==_typeof(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}var ExoManager=function(){function e(){_classCallCheck(this,e),this.label="ExoManager",this.doDebug=!1,this.settingsGroup="",this.instanceSettingsGroup="",this.ready=!1,this.build()}return _createClass(e,[{key:"build",value:function(){this.instances=new ExoCollection}},{key:"attach",value:function(e){var n=this;return this.ready=!1,new Promise(function(t,e){n.debug("log","Attach: Start",n.settingsGroup,n.instanceSettingsGroup),n.buildInstances().then(function(e){n.ready=!0,t(e),n.debug("info","Attach: Finish",e)})})}},{key:"buildInstances",value:function(){var s=this;return new Promise(function(t,e){var n=[],i=s.getSettingsGroup(s.instanceSettingsGroup);if(null===i||Array.isArray(i)&&!i.length||"object"===_typeof(i)&&jQuery.isEmptyObject(i))s.debug("log","Build Instances: Empty"),t(!0);else{for(var o in s.debug("log","Build Instances: Start",i),s.time=Date.now(),i)i.hasOwnProperty(o)&&n.push(s.buildInstance(o,i[o]));Promise.all(n).then(function(e){s.debug("info","Build Instances: Finish",e),s.time=Date.now(),t(!0)},function(e){t(!1)})}})}},{key:"buildInstance",value:function(n,i){var o=this,s=this.instances.getById(n);return s||(s=this.createInstance(n,i),this.addInstance(n,s)),new Promise(function(t,e){i._exoTime=o.time,o.debug("log","Build Instance: Start","["+n+"]",i),s.build(i).then(function(e){o.debug("info","Build Instance: Finish","["+n+"]",status),null!==e&&(""!==o.instanceSettingsGroup&&o.purgeSetting(o.instanceSettingsGroup,n),s.afterBuild()),t(!0)},e)})}},{key:"create",value:function(n,i){var o=this;return new Promise(function(t,e){o.buildInstance(n,i).then(function(e){t(e)})})}},{key:"isReady",value:function(){var n=this;return new Promise(function(t,e){(function e(){!0===n.ready?(n.debug("info","Is Ready"),t(n.getInstances())):setTimeout(e,20)})()})}},{key:"createInstance",value:function(e,t){return new this.instanceClass(e)}},{key:"addInstance",value:function(e,t){this.instances.add(e,t)}},{key:"removeInstance",value:function(e){this.instances.remove(e)}},{key:"getInstances",value:function(){return this.instances}},{key:"getInstance",value:function(e){return this.getInstances().getById(e)}},{key:"getSettingsGroup",value:function(e){return drupalSettings[this.settingsGroup]&&drupalSettings[this.settingsGroup][e]?drupalSettings[this.settingsGroup][e]:null}},{key:"purgeSettingsGroup",value:function(e){return drupalSettings[this.settingsGroup]&&drupalSettings[this.settingsGroup][e]&&(this.debug("warn","Purge Settings Group",this.settingsGroup,e),delete drupalSettings[this.settingsGroup][e]),this}},{key:"getSetting",value:function(e,t){e=this.getSettingsGroup(e);return e&&e[t]?e[t]:null}},{key:"purgeSetting",value:function(e,t){var n=this.getSettingsGroup(e);return n&&n[t]&&(this.debug("warn","Purge Settings",e,t,n[t]),delete n[t]),this}},{key:"debug",value:function(e){if(!0===this.doDebug){for(var t,n=arguments.length,i=new Array(1<n?n-1:0),o=1;o<n;o++)i[o-1]=arguments[o];(t=Drupal.Exo).debug.apply(t,[e,this.label].concat(i))}}}]),e}(),ExoCollection=function(){function t(e){_classCallCheck(this,t),this._items={},e&&(this._items=e)}return _createClass(t,[{key:"add",value:function(e,t){return this._items[e]=t,this}},{key:"remove",value:function(e){return delete this._items[e],this}},{key:"each",value:function(e){return _.each(this.getAll(),e),this}},{key:"count",value:function(){return _.keys(this._items).length}},{key:"has",value:function(e){return void 0!==this._items[e]}},{key:"getAll",value:function(){return this._items}},{key:"getFirst",value:function(){var e;return this.count()?(e=this.getAll())[_.keys(e)[0]]:null}},{key:"getLast",value:function(){var e;return this.count()?(e=this.getAll())[_.keys(e)[_.keys(e).length-1]]:null}},{key:"getNext",value:function(e,t){var n=this.getAll(),i=_.keys(n),e=i.indexOf(e);return void 0!==n[i[e+1]]?n[i[e+1]]:t?this.getFirst():null}},{key:"getPrev",value:function(e,t){var n=this.getAll(),i=_.keys(n),e=i.indexOf(e);return void 0!==n[i[e-1]]?n[i[e-1]]:t?this.getLast():null}},{key:"getById",value:function(e){return this.has(e)?this._items[e]:null}},{key:"getByDelta",value:function(e){var t=this.getAll(),n=_.keys(t);return n[e]?t[n[e]]:null}}]),t}(),ExoData=function(){function t(e){_classCallCheck(this,t),this.label="ExoData",this.doDebug=!1,this.dataOriginal={},this.data={},this.defaults={},this.events={},this.id=e}return _createClass(t,[{key:"build",value:function(n,i){var o=this;return i=!1!==i,new Promise(function(e,t){n=jQuery.extend(!0,{},n),o.debug("log","Build: Start","["+o.id+"]",n),_.isEqual(n,o.dataOriginal)?(o.debug("warn","Build: Skip (same data)","["+o.id+"]",n,o.dataOriginal),t()):(t=jQuery.extend(!0,{},n),i&&(n=o.difference(n,o.dataOriginal)),n=jQuery.extend(!0,{},o.defaults,n),n=Drupal.Exo.cleanData(n,o.defaults),o.data=jQuery.extend(!0,{},o.data,n),o.dataOriginal=t,o.debug("info","Build: Finish","["+o.id+"]",o.data),e(n))})}},{key:"afterBuild",value:function(){}},{key:"get",value:function(e){return void 0!==this.data[e]?this.data[e]:null}},{key:"set",value:function(e,t){return this.data[e]=t,this}},{key:"getId",value:function(){return this.id}},{key:"getData",value:function(){return this.data}},{key:"getWeight",value:function(){return this.get("weight")}},{key:"setWeight",value:function(e){return this.set("weight",e),this}},{key:"event",value:function(e){return void 0!==this.events[e]?this.events[e].expose():null}},{key:"difference",value:function(e,t){return function n(e,i){return _.pick(_.mapObject(e,function(e,t){return _.isEqual(e,i[t])?null:_.isObject(e)&&_.isObject(i[t])?n(e,i[t]):e}),function(e){return null!==e})}(e,t)}},{key:"debug",value:function(e){if(!0===this.doDebug){for(var t,n=arguments.length,i=new Array(1<n?n-1:0),o=1;o<n;o++)i[o-1]=arguments[o];(t=Drupal.Exo).debug.apply(t,[e,this.label].concat(i))}}}]),t}(),ExoDataManager=function(){_inherits(t,ExoManager);var e=_createSuper(t);function t(){return _classCallCheck(this,t),e.apply(this,arguments)}return _createClass(t,[{key:"build",value:function(){this.instances=new ExoDataCollection}},{key:"addInstance",value:function(e,t){this.instances.add(t)}}]),t}(),ExoDataCollection=function(){_inherits(t,ExoCollection);var e=_createSuper(t);function t(){return _classCallCheck(this,t),e.apply(this,arguments)}return _createClass(t,[{key:"add",value:function(e){return e instanceof ExoData&&(_get(_getPrototypeOf(t.prototype),"add",this).call(this,e.getId(),e),this._items=this.sortKeysBy(this._items,function(e,t){return e.getWeight()})),this}},{key:"elements",value:function(){if(!this.$elements){var e,t=[];for(e in this._items)t.push(this._items[e].getSelector());this.$elements=jQuery(t.join(", "))}return this.$elements}},{key:"getData",value:function(){var t={};return this.each(function(e){t[e.getId()]=e.getData()}),t}},{key:"sortKeysBy",value:function(t,n){var e=_.sortBy(_.keys(t),function(e){return n?n(t[e],e):e});return _.object(e,_.map(e,function(e){return t[e]}))}}]),t}(),ExoEvent=function(){function e(){_classCallCheck(this,e),this.handlers={}}return _createClass(e,[{key:"on",value:function(e,t){this.handlers[e]=t}},{key:"off",value:function(e){delete this.handlers[e]}},{key:"trigger",value:function(e){for(var t in this.handlers)void 0!==this.handlers[t]?this.handlers[t](e):console.log(t,this)}},{key:"expose",value:function(){return this}}]),e}();!function(u,o,c,h){var n,r,l,e=function(){function e(){var n=this;_classCallCheck(this,e),this.label="Exo",this.doDebug=!1,this.resizeCallbacks={},this.initPromises=[],this.revealPromises=[],this.initialized=!1,this.shadowUsage=0,this.shadowCallbacks=[],this.scrollThrottle=99,this.observer={},this.observerThreshold=[0,1],this.styleProps={},this.observables=[],this.events={init:new ExoEvent,ready:new ExoEvent,reveal:new ExoEvent,breakpoint:new ExoEvent},this.speed=300,this.breakpoint={min:null,max:null,name:null},this.$window=u(window),this.$document=u(document),this.$body=u("body"),this.$exoBody=u("#exo-body"),this.$exoCanvas=u("#exo-canvas"),this.$exoContent=u("#exo-content"),this.$exoShadow=u("#exo-shadow"),this.$exoStyle=u('<style id="exo-style" type="text/css" />').appendTo("head"),this.animationEvent=this.whichAnimationEvent(),this.transitionEvent=this.whichTransitionEvent(),this.refreshBreakpoint(),this.$exoShadow.on("click.exoShowShadow",function(e){var t=n.shadowCallbacks[n.shadowCallbacks.length-1];t&&t(e)}),this.isFirefox()?this.$body.addClass("is-firefox"):this.isIE()&&this.$body.addClass("is-ie"),this.isTouch()?this.$body.addClass("has-touch"):this.$body.addClass("no-touch")}return _createClass(e,[{key:"init",value:function(){var e=this;this.debug("log",this.label,"Init"),setTimeout(function(){e.doInit()})}},{key:"doInit",value:function(){var t=this,n=(this.event("init").trigger(),this.onResize(),setTimeout(function(){t.ready()},3e3)),i=(this.debug("log",this.label,"Init Promises",this.initPromises),Promise.all(this.initPromises).then(function(e){clearTimeout(n),setTimeout(function(){t.ready()})}),this.$exoBody.imagesLoaded(function(){!0===t.initialized&&t.displaceContent(),setTimeout(function(){void 0!==c.drimage&&c.drimage.init()})}),o.throttle(function(){t.onResize()},99));this.$window.on("resize.exo",function(e){t.refreshBreakpoint(),i()})}},{key:"attach",value:function(){this.event("init").trigger(),this.event("ready").trigger(),this.$document.trigger("exoReady"),this.event("reveal").trigger(),this.$document.trigger("exoReveal"),this.checkElementPosition()}},{key:"ready",value:function(){var t=this;this.debug("log",this.label,"Ready"),this.initialized=!0,this.$body.addClass("exo-ready"),this.event("ready").trigger(),this.$document.trigger("exoReady"),this.displaceContent(),this.resizeContent(),this.debug("log",this.label,"Reveal Promises",this.revealPromises),Promise.all(this.revealPromises).then(function(e){t.debug("log",t.label,"Reveal"),t.event("reveal").trigger(),t.$document.trigger("exoReveal")})}},{key:"isInitialized",value:function(){return this.initialized}},{key:"addInitWait",value:function(e){this.debug("log",this.label,"Init Wait Added"),this.initPromises.push(e)}},{key:"addRevealWait",value:function(e){this.debug("log",this.label,"Reveal Wait Added"),this.revealPromises.push(e)}},{key:"getBodyElement",value:function(){return this.$exoBody}},{key:"onResize",value:function(){if(this.debug("log",this.label,"onResize"),c.ExoDisplace.calculate(),!0===this.initialized&&this.resizeWidth!==window.innerWidth)for(var e in this.displaceContent(),this.resizeContent(),this.resizeCallbacks)this.resizeCallbacks.hasOwnProperty(e)&&this.resizeCallbacks[e]();this.resizeWidth=window.innerWidth,this.checkElementPosition()}},{key:"addOnResize",value:function(e,t){this.resizeCallbacks[e]=t}},{key:"removeOnResize",value:function(e){void 0!==this.resizeCallbacks[e]&&delete this.resizeCallbacks[e]}},{key:"addStyleProp",value:function(e,t){this.styleProps[e]=t}},{key:"removeStyleProp",value:function(e){void 0!==this.styleProps[e]&&delete this.styleProps[e]}},{key:"updateStyle",value:function(){var e,t=":root {";for(e in this.styleProps)this.styleProps.hasOwnProperty(e)&&(t+="--"+e+": "+this.styleProps[e]+";");this.$exoStyle.html(t+="}")}},{key:"displaceContent",value:function(t){var e,n=this;t=t||h.offsets,this.debug("log",this.label,"displaceContent",t),this.$exoBody.css({paddingTop:t.top,paddingBottom:t.bottom,paddingLeft:t.left,paddingRight:t.right}),this.addStyleProp("displace-top",t.top+"px"),this.addStyleProp("displace-bottom",t.bottom+"px"),this.addStyleProp("displace-left",t.left+"px"),this.addStyleProp("displace-right",t.right+"px"),this.updateStyle(),["top","right","bottom","left"].forEach(function(e){t[e]&&(n.$exoBody.find(".exo-displace-"+e).css("top",t[e]+"px"),n.$exoBody.find(".exo-displace-padding-"+e).css("padding-"+e,t[e]+"px"),n.$exoBody.find(".exo-displace-margin-"+e).css("margin-"+e,t[e]+"px"))}),window.localStorage&&window.localStorage.setItem("exoBodySize",JSON.stringify(t)),void 0!==drupalSettings.gin&&!0===drupalSettings.path.currentPathIsAdmin&&(u(".layout-region-node-secondary").css({top:t.top+"px",height:"calc(100% - "+t.top+"px)"}),u(".layout-region-node-actions").css({top:t.top+"px"}),(e=u(".region-sticky")).css({top:t.top+"px"}),u(".sticky-shadow").css({position:"absolute",top:"100%",left:0,right:0}).appendTo(e))}},{key:"resizeContent",value:function(){var e=this.$window.height()-(parseInt(this.$exoBody.css("paddingTop"))+parseInt(this.$exoBody.css("paddingBottom")));this.debug("log",this.label,"resizeContent",e),this.$exoContent.css("min-height",e),window.localStorage&&window.localStorage.setItem("exoContentHeight",String(this.$exoContent.height()))}},{key:"showShadow",value:function(n){var i=this;return n=o.extend({opacity:.8,onClick:null},n),this.shadowUsage++,n.onClick&&this.shadowCallbacks.push(n.onClick),new Promise(function(e,t){clearTimeout(i.exoShadowTimeout),i.$exoShadow.addClass("active"),i.exoShadowTimeout=setTimeout(function(){i.$exoShadow.addClass("animate").css("opacity",n.opacity),e()},20)})}},{key:"hideShadow",value:function(){var n=this;return new Promise(function(e,t){n.shadowUsage--,n.shadowCallbacks.pop(),n.shadowUsage<=0?(n.shadowUsage=0,n.shadowCallbacks=[],clearTimeout(n.exoShadowTimeout),n.$exoShadow.removeClass("animate").css("opacity",0),n.exoShadowTimeout=setTimeout(function(){n.$exoShadow.removeClass("active"),e()},n.speed)):e()})}},{key:"setScrollThrottle",value:function(e){this.scrollThrottle=e}},{key:"setObserverThreshold",value:function(e){this.observerThreshold=e}},{key:"observeElement",value:function(n,i,o,s,a,r){var l=this;return new Promise(function(e,t){a=a||"exo",void 0===l.observer[a]&&(r=r||{threshold:l.observerThreshold},l.observer[a]=new IntersectionObserver(l.observed,r)),n.each(function(e,t){l.observables.push({inViewportCallback:i||null,outViewportCallback:o||null,observedCallback:s||null}),t.dataset.exoActive="false",t.dataset.exoObserverId=a,t.dataset.exoObservableId=(l.observables.length-1).toString(),l.observer[a].observe(t)}),e()})}},{key:"observed",value:function(e){e.forEach(function(e){var t=e.target,n=u(t),i=e.intersectionRatio,o=e.boundingClientRect,s=Math.round(window.scrollY+h.offsets.top),a=s+Math.round(window.innerHeight-h.offsets.top-h.offsets.bottom),r="true"===t.dataset.exoActive,l=c.Exo.observables[parseInt(t.dataset.exoObservableId)];"function"==typeof l.observedCallback&&l.observedCallback(n,s,a,o,e),0===i?r&&(t.dataset.exoActive="false","function"==typeof l.outViewportCallback)&&l.outViewportCallback(n,s,a,o,e):r||(t.dataset.exoActive="true","function"==typeof l.inViewportCallback&&l.inViewportCallback(n,s,a,o,e))})}},{key:"trackElementPosition",value:function(n,i,o,s,a){var r=this;return new Promise(function(e,t){n instanceof HTMLElement&&(n=u(n)),r.untrackElementPosition(n),n.once("exo.track.position").length&&("function"!=typeof i&&"function"!=typeof o&&"function"!=typeof a||r.observeElement(n,i,o,a),_typeof(s))&&r.trackElementScroll(n,s),e()})}},{key:"trackElementScroll",value:function(e,t){var n=this;this.$elementPositions||(this.$elementPositions=u(),this.$window.on("scroll.exo",o.throttle(function(e){return n.checkElementPosition()},this.scrollThrottle))),e.data("exoScrollCallback",t),this.$elementPositions=this.$elementPositions.add(e),this.checkElementPosition()}},{key:"untrackElementPosition",value:function(e){(e=e instanceof HTMLElement?u(e):e).findOnce("exo.track.position").length&&(e.removeOnce("exo.track.position"),e[0].dataset.exoObservableId&&this.observer[e[0].dataset.exoObserverId].unobserve(e[0]),this.$elementPositions)&&this.$elementPositions.length&&(this.$elementPositions=this.$elementPositions.not(e))}},{key:"checkElementPosition",value:function(){var o,e,s;void 0!==this.$elementPositions&&this.$elementPositions.length&&(o=window.scrollY+h.offsets.top,e=window.innerHeight-h.offsets.top-h.offsets.bottom,s=o+e,this.$elementPositions.each(function(e,t){var n=u(t),i=n.data("exoScrollCallback");"function"==typeof i&&i(n,o,s,t.getBoundingClientRect())}))}},{key:"cleanElementPosition",value:function(n){var i=this;this.$elementPositions&&this.$elementPositions.length&&this.$elementPositions.each(function(e,t){t=u(t);t.closest(n).length&&(i.$elementPositions=i.$elementPositions.not(t))})}},{key:"refreshBreakpoint",value:function(){var t={};String(window.getComputedStyle(document.querySelector("body"),":before").getPropertyValue("content")).split("|").forEach(function(e){e=e.replace('"',"").split(":");t[e[0]]=e[1]}),t.min!==this.breakpoint.min&&(this.breakpoint=t,this.event("breakpoint").trigger(t))}},{key:"lockOverflow",value:function(e,t){e?(e instanceof HTMLElement&&(e=u(e)),bodyScrollLock.disableBodyScroll(e.get(0),t)):(u("body").css("top",-document.documentElement.scrollTop+"px"),u("html").addClass("exo-lock-overflow"))}},{key:"unlockOverflow",value:function(e){var t;e?(e instanceof HTMLElement&&(e=u(e)),bodyScrollLock.enableBodyScroll(e.get(0))):(t=-1*parseInt(u("body").css("top")),u("body").css("top",""),u("html").removeClass("exo-lock-overflow"),t&&setTimeout(function(){window.scrollTo(0,t)}))}},{key:"getMeasurementValue",value:function(e){return parseInt(String(e).split(/%|px|em|cm|vh|vw/)[0])}},{key:"getMeasurementUnit",value:function(e){return String(e).match(/[\d.\-\+]*\s*(.*)/)[1]||""}},{key:"getPxFromEm",value:function(e){return(e=this.getMeasurementValue(String(e)))*parseFloat(getComputedStyle(document.querySelector("body"))["font-size"])}},{key:"isIE",value:function(e){return 9===e?-1!==navigator.appVersion.indexOf("MSIE 9."):-1<(e=navigator.userAgent).indexOf("MSIE ")||-1<e.indexOf("Trident/")}},{key:"isMobile",value:function(){return"small"===this.breakpoint.name}},{key:"isFirefox",value:function(){return-1<navigator.userAgent.toLowerCase().indexOf("firefox")}},{key:"isIos",value:function(){return/iPhone|iPod/.test(navigator.userAgent)&&!window.MSStream}},{key:"isIpadOs",value:function(){return/iPad/.test(navigator.userAgent)&&!window.MSStream}},{key:"isSafari",value:function(){return void 0!==window.safari&&!this.isIos()&&!this.isIpadOs()}},{key:"isTouch",value:function(){return"ontouchstart"in document.documentElement}},{key:"whichAnimationEvent",value:function(){var e,t=document.createElement("fakeelement"),n={animation:"animationend",OAnimation:"oAnimationEnd",MozAnimation:"animationend",WebkitAnimation:"webkitAnimationEnd"};for(e in n)if(void 0!==t.style[e])return n[e]}},{key:"guid",value:function(){function e(){return Math.floor(65536*(1+Math.random())).toString(16).substring(1)}return e()+e()+"-"+e()+"-"+e()+"-"+e()+"-"+e()+e()+e()}},{key:"whichTransitionEvent",value:function(){var e,t=document.createElement("fakeelement"),n={transition:"transitionend",OTransition:"oTransitionEnd",MozTransition:"transitionend",WebkitTransition:"webkitTransitionEnd"};for(e in n)if(void 0!==t.style[e])return n[e]}},{key:"event",value:function(e){return void 0!==this.events[e]?this.events[e].expose():null}},{key:"stringToCallback",value:function(e){var t,n=null;return"string"==typeof e&&(e=e.split("."),t=window,e.forEach(function(e){t[e]&&("function"==typeof t[e]&&"object"===_typeof(t)?n={object:t,function:e}:t=t[e])})),n}},{key:"cleanData",value:function(n,i){return jQuery.each(n,function(e,t){"true"==t?n[e]=!0:"false"==t?n[e]=!1:"1"!==t&&1!==t||"boolean"!=typeof i[e]?"0"!==t&&0!==t||"boolean"!=typeof i[e]?/^\d+$/.test(t)&&(n[e]=parseInt(t)):n[e]=!1:n[e]=!0}),n}},{key:"toCamel",value:function(e){return e.replace(/[-_]+([a-z])/g,function(e){return e[1].toUpperCase()})}},{key:"toSnake",value:function(e){return e.replace(/([A-Z])/g,"_$1").toLowerCase()}},{key:"toDashed",value:function(e){return e.replace(/([A-Z])/g,"-$1").toLowerCase()}},{key:"debug",value:function(e,t){var n;if(t!==this.label||!1!==this.doDebug){for(var i=arguments.length,o=new Array(2<i?i-2:0),s=2;s<i;s++)o[s-2]=arguments[s];switch(e){case"info":(n=console).info.apply(n,["[eXo "+t+"]"].concat(o));break;case"warn":(n=console).warn.apply(n,["[eXo "+t+"]"].concat(o));break;case"error":(n=console).error.apply(n,["[eXo "+t+"]"].concat(o));break;default:(n=console).log.apply(n,["[eXo "+t+"]"].concat(o))}}}}]),e}(),e=(c.Exo=new e,function(){function e(){_classCallCheck(this,e),this.offsets={top:0,right:0,bottom:0,left:0},this.events={calculate:new ExoEvent,changed:new ExoEvent}}return _createClass(e,[{key:"calculate",value:function(){var e=this.calculateOffsets(),t=e!==this.offsets;return this.offsets=e,this.event("calculate").trigger(this.offsets),t&&this.broadcast(),this.offsets}},{key:"broadcast",value:function(){this.event("changed").trigger(this.offsets)}},{key:"calculateOffsets",value:function(){return{top:this.calculateOffset("top"),right:this.calculateOffset("right"),bottom:this.calculateOffset("bottom"),left:this.calculateOffset("left")}}},{key:"calculateOffset",value:function(e){for(var t=0,n=document.querySelectorAll("[data-exo-edge='".concat(e,"']")),i=n.length,o=0;o<i;o++){var s,a=n[o];"none"!==a.style.display&&"hidden"!==a.style.visibility&&(s=parseInt(a.getAttribute("data-exo-edge='".concat(e,"'")),10),isNaN(s)&&(s=this.getRawOffset(a,e)),t=Math.max(t,s))}return t}},{key:"getRawOffset",value:function(e,t){var n=u(e),i=document.documentElement,o=0,e="left"===t||"right"===t,s=n.offset()[e?"left":"top"];switch(s-=window["scroll".concat(e?"X":"Y")]||document.documentElement["scroll".concat(e?"Left":"Top")]||0,t){case"top":o=s+n.outerHeight();break;case"left":o=s+n.outerWidth();break;case"bottom":o=i.clientHeight-s;break;case"right":o=i.clientWidth-s;break;default:o=0}return o}},{key:"event",value:function(e){return void 0!==this.events[e]?this.events[e].expose():null}}]),e}()),d=(c.ExoDisplace=new e,c.behaviors.exo={},document.body.style.position="relative",setTimeout(function(){c.Exo.init(document.body)},1e3));"undefined"!=typeof loadCSS?(c.Exo.debug("log","Exo","Found loadCSS"),n=function(e){return e.replace("http://","").replace("https://","").split("/")[0]},(r=u('link[rel="stylesheet"]').filter(function(e,t){return!(!location.href||!t.href)&&n(location.href)===n(t.href)})).length?(c.Exo.debug("log","Exo","Sheets to Load",r),l=0,r.each(function(e,t){var n,i=u(t).prop("href"),o=(c.Exo.debug("log","Exo","Load",i),t),s=function(){++l==r.length&&(clearTimeout(d),c.Exo.init(document.body))};function a(){!n&&s&&(n=!0,s.call(o))}o.addEventListener&&o.addEventListener("load",a),o.attachEvent&&o.attachEvent("onload",a),"isApplicationInstalled"in navigator&&"onloadcssdefined"in o&&o.onloadcssdefined(a)})):(clearTimeout(d),c.Exo.init(document.body))):(clearTimeout(d),c.behaviors.exo.attach=function(e){c.Exo.init(document.body),c.behaviors.exo.attach=function(e){c.Exo.attach(document.body)}}),c.behaviors.exo.detach=function(e,t,n){"unload"===n&&c.Exo.cleanElementPosition(e)}}(jQuery,_,Drupal,Drupal.displace);