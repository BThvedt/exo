"use strict";function _typeof(e){return(_typeof="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function _defineProperties(e,t){for(var n=0;n<t.length;n++){var a=t[n];a.enumerable=a.enumerable||!1,a.configurable=!0,"value"in a&&(a.writable=!0),Object.defineProperty(e,a.key,a)}}function _createClass(e,t,n){return t&&_defineProperties(e.prototype,t),n&&_defineProperties(e,n),e}function _possibleConstructorReturn(e,t){return!t||"object"!==_typeof(t)&&"function"!=typeof t?_assertThisInitialized(e):t}function _assertThisInitialized(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}function _get(e,t,n){return(_get="undefined"!=typeof Reflect&&Reflect.get?Reflect.get:function(e,t,n){var a=_superPropBase(e,t);if(a){var r=Object.getOwnPropertyDescriptor(a,t);return r.get?r.get.call(n):r.value}})(e,t,n||e)}function _superPropBase(e,t){for(;!Object.prototype.hasOwnProperty.call(e,t)&&null!==(e=_getPrototypeOf(e)););return e}function _getPrototypeOf(e){return(_getPrototypeOf=Object.setPrototypeOf?Object.getPrototypeOf:function(e){return e.__proto__||Object.getPrototypeOf(e)})(e)}function _inherits(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),t&&_setPrototypeOf(e,t)}function _setPrototypeOf(e,t){return(_setPrototypeOf=Object.setPrototypeOf||function(e,t){return e.__proto__=t,e})(e,t)}!function(c){var e=function(){function n(e){var t;return _classCallCheck(this,n),(t=_possibleConstructorReturn(this,_getPrototypeOf(n).call(this,e))).defaults={debug:!1,backNav:0,backText:"Back",backIcon:"",breadcrumbNav:1,backSelector:".exo-menu-top",breadcrumbText:"All",breadcrumbIcon:"",breadcrumbSeparatorIcon:"",breadcrumbSelector:".exo-menu-top",itemIcon:"",itemDelayInterval:60,direction:"r2l",theme:"",relocateNav:"",onItemClick:null},t.menuStorage={},t}return _inherits(n,ExoMenuStyleBase),_createClass(n,[{key:"build",value:function(){_get(_getPrototypeOf(n.prototype),"build",this).call(this),this.buildCache(),this.$menus.length&&(this.buildElement(),this.bindEvents())}},{key:"refresh",value:function(){this.menuStorage[this.currentMenu]&&this.setWrapHeight(this.menuStorage[this.currentMenu].$menuEl)}},{key:"buildCache",value:function(){this.$menu=this.$element.find(".exo-menu-nav"),this.$wrap=this.$menu.find(".exo-menu-wrap"),this.$menus=this.$menu.find(".exo-menu-level"),this.get("breadcrumbNav")&&(this.$breadcrumbWrapper=this.$element.find(".exo-menu-top")),this.get("backNav")&&(this.$backWrapper=this.$element.find(".exo-menu-top"));var n="0-0";this.$menus.each(function(e,t){c(t).find(".exo-menu-link.is-active").length&&(n=c(t).data("menu"))}),this.currentMenu=n}},{key:"buildElement",value:function(){var e,u=this;if(this.$menus.each(function(e,t){var n=c(t),a=n.data("menu"),r=n.data("menu-parent"),i={$menuEl:n,$menuItems:n.find(".exo-menu-item"),backIdx:r,name:"0-0"===a?u.get("breadcrumbText"):u.$menus.find('.exo-menu-link[data-submenu="'+a+'"]').html()};u.menuStorage[a]=i,a===u.currentMenu&&(n.addClass("current"),u.setWrapHeight(n)),n.find(".exo-menu-link[data-submenu]").each(function(e,t){var n=c(t);u.get("itemIcon")&&n.append(u.get("itemIcon"))})}),this.get("backNav")&&(e=this.fetchSelector(this.get("backSelector")))){e.find(".exo-menu-back").remove(),this.$backNav=c('<a class="exo-menu-back" aria-label="'+this.get("backText")+'" href="#"></a>').prependTo(e);var t=this.get("backText");this.get("backIcon")&&(t=this.get("backIcon")+" "+t),this.$backNav.html(t),"0-0"!==this.currentMenu&&this.$backNav.addClass("animate-fadeIn")}this.get("breadcrumbNav")&&(e=this.fetchSelector(this.get("breadcrumbSelector")))&&(e.find(".exo-menu-breadcrumbs").remove(),this.$breadcrumbNav=c('<nav class="exo-menu-breadcrumbs" aria-label="You are here"></nav>').prependTo(e),this.crawlCrumbs(this.menuStorage[this.currentMenu].backIdx,this.menuStorage),this.addBreadcrumb(this.currentMenu))}},{key:"bindEvents",value:function(){var u=this;for(var e in this.menuStorage)this.menuStorage.hasOwnProperty(e)&&this.menuStorage[e].$menuItems.each(function(i,e){c(e).find(".exo-menu-link[data-submenu]").on("click",function(e){var t=c(e.currentTarget),n=t.html(),a=t.attr("data-submenu"),r=u.$menu.find('.exo-menu-level[data-menu="'+a+'"]');r.length?(e.preventDefault(),u.openSubMenu(r,i,n)):(u.$menu.find(".current").removeClass("current"),t.addClass("current"))})});this.get("backNav")&&this.$backNav.on("click",function(e){e.preventDefault(),u.back()})}},{key:"addBreadcrumb",value:function(a){var r=this;if(this.get("breadcrumbNav")){var e=document.createElement("div");e.innerHTML=this.menuStorage[a].name;var t=e.innerText,i=c('<span class="exo-menu-breadcrumb">');"0-0"===a&&this.get("breadcrumbIcon")?t=this.get("breadcrumbIcon")+t:this.get("breadcrumbSeparatorIcon")&&i.html('<span class="exo-menu-seperator">'+this.get("breadcrumbSeparatorIcon")+"</span>"),c('<a href="#"></a>').html(t).appendTo(i).on("click",function(e){if(e.preventDefault(),!i.next().length||r.isAnimating)return!1;r.isAnimating=!0,r.menuOut();var t=r.menuStorage[a].$menuEl;r.menuIn(t);var n=i.nextAll();n.one(Drupal.Exo.animationEvent,function(e){n.remove()}).addClass("animate-fadeOut")}),this.$breadcrumbNav.append(i),i.addClass("animate-fadeIn")}}},{key:"openSubMenu",value:function(e,t,n){if(this.isAnimating)return!1;var a=e.data("menu");this.isAnimating=!0,this.menuStorage[a].backIdx=this.currentMenu,this.menuStorage[a].name=n,this.menuOut(t),this.menuIn(e,t)}},{key:"back",value:function(){if(this.isAnimating)return!1;this.isAnimating=!0,this.menuOut();var e=this.menuStorage[this.menuStorage[this.currentMenu].backIdx].$menuEl;this.menuIn(e),this.get("breadcrumbNav")&&this.$breadcrumbNav.children().last().remove()}},{key:"menuIn",value:function(n,a){var r=this,i=this.menuStorage[this.currentMenu].$menuEl,u=void 0===a,o=n.data("menu"),e=this.menuStorage[o].$menuItems,s=e.length;this.setWrapHeight(n),u?"0-0"===o&&this.get("backNav")&&this.$backNav.removeClass("animate-fadeIn").addClass("animate-fadeOut"):(this.get("backNav")&&this.$backNav.removeClass("animate-fadeOut").addClass("animate-fadeIn"),this.addBreadcrumb(o)),e.each(function(e,t){t.style.webkitAnimationDelay=t.style.animationDelay=u?e*r.get("itemDelayInterval")+"ms":Math.abs(a-e)*r.get("itemDelayInterval")+"ms",e===(a<=s/2||u?s-1:0)&&c(t).one(Drupal.Exo.animationEvent,function(e){"r2l"===r.get("direction")?(i.removeClass(u?"animate-fadeOutRight":"animate-fadeOutLeft"),n.removeClass(u?"animate-fadeInLeft":"animate-fadeInRight")):(i.removeClass(u?"animate-fadeOutLeft":"animate-fadeOutRight"),n.removeClass(u?"animate-fadeInRight":"animate-fadeInLeft")),i.removeClass("current"),n.addClass("current"),r.currentMenu=o,r.isAnimating=!1,n.focus()})}),"r2l"===this.get("direction")?n.addClass(u?"animate-fadeInLeft":"animate-fadeInRight"):n.addClass(u?"animate-fadeInRight":"animate-fadeInLeft")}},{key:"menuOut",value:function(n){var a=this,e=this.menuStorage[this.currentMenu].$menuEl,r=void 0===n;this.menuStorage[this.currentMenu].$menuItems.each(function(e,t){t.style.webkitAnimationDelay=t.style.animationDelay=r?e*a.get("itemDelayInterval")+"ms":Math.abs(n-e)*a.get("itemDelayInterval")+"ms"}),"r2l"===this.get("direction")?e.addClass(r?"animate-fadeOutRight":"animate-fadeOutLeft"):e.addClass(r?"animate-fadeOutLeft":"animate-fadeOutRight")}},{key:"crawlCrumbs",value:function(e,t){0!==e&&("0-0"!==t[e].backIdx&&this.crawlCrumbs(t[e].backIdx,t),this.addBreadcrumb(e))}},{key:"setWrapHeight",value:function(t){var a=this;setTimeout(function(){t=t||a.$menus.filter(".current");var e=a.$wrap.height(),n=0;t.children().each(function(e,t){n+=c(t).outerHeight()}),e<=n?a.$wrap.height(n):t.one(Drupal.Exo.animationEvent,function(e){a.$wrap.height(n)})},10)}},{key:"fetchSelector",value:function(e){var t=this.$element.find(e);return t.length||(t=c(e).first()),t.length?t:null}},{key:"log",value:function(e){this.get("debug")&&("object"===_typeof(e)?console.log("[Exo Menu]",e):console.log("[Exo Menu] "+e))}}]),n}();Drupal.ExoMenuStyles.slide_vertical=e}(jQuery);