"use strict";function _typeof(e){return(_typeof="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function _defineProperties(e,t){for(var n=0;n<t.length;n++){var i=t[n];i.enumerable=i.enumerable||!1,i.configurable=!0,"value"in i&&(i.writable=!0),Object.defineProperty(e,_toPropertyKey(i.key),i)}}function _createClass(e,t,n){return t&&_defineProperties(e.prototype,t),n&&_defineProperties(e,n),Object.defineProperty(e,"prototype",{writable:!1}),e}function _toPropertyKey(e){e=_toPrimitive(e,"string");return"symbol"===_typeof(e)?e:String(e)}function _toPrimitive(e,t){if("object"!==_typeof(e)||null===e)return e;var n=e[Symbol.toPrimitive];if(void 0===n)return("string"===t?String:Number)(e);n=n.call(e,t||"default");if("object"!==_typeof(n))return n;throw new TypeError("@@toPrimitive must return a primitive value.")}!function(o,a,s){var i=function(){function t(e,i){function n(){var t,e=o.$wrapper.data("ee--split-text-type")||"lines",n=o.$wrapper.data("ee--split-text-delay-lines"),e=new s(i[0],{types:e,lineClass:"split-text-line",wordClass:"split-text-word",charClass:"split-text-char"});n&&(t=0,e.lines.forEach(function(e){e.style.setProperty("transition-delay",t*parseInt(n)+"ms"),t++})),setTimeout(function(){o.$wrapper.addClass("loaded")},100)}var o=this,r=(_classCallCheck(this,t),this.id="",this.$wrapper=i,this.id=e,null);document.fonts.onloadingdone=function(){console.log("hit")},document.fonts.ready.then(function(){o.$wrapper.imagesLoaded(function(){var t=o.$wrapper.clone();n(),a.Exo.addOnResize("exo.alchemist.enhancement.splitText."+o.id,function(e){clearTimeout(r),o.$wrapper.removeClass("loaded").html(t.html()),r=setTimeout(function(){n()},500)})})})}return _createClass(t,[{key:"unload",value:function(){a.Exo.removeOnResize("exo.alchemist.enhancement.splitText."+this.id)}}]),t}();a.behaviors.exoAlchemistEnhancementSplitText={count:0,instances:{},attach:function(e){var n=this;o(".ee--split-text-wrapper",e).once("exo.alchemist.enhancement").each(function(){var e=o(this),t=e.data("ee--split-text-id");e.data("ee--split-text-count",n.count),n.instances[t+n.count]=new i(t,e),n.count++})},detach:function(e,t,n){var i;"unload"===n&&(i=this,o(".ee--split-text-wrapper",e).each(function(){var e=o(this),e=e.data("ee--split-text-id")+e.data("ee--split-text-count");void 0!==i.instances[e]&&(i.instances[e].unload(),delete i.instances[e])}))}}}(jQuery,Drupal,SplitType);