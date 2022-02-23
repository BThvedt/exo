"use strict";function _classCallCheck(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function _defineProperties(e,t){for(var n=0;n<t.length;n++){var i=t[n];i.enumerable=i.enumerable||!1,i.configurable=!0,"value"in i&&(i.writable=!0),Object.defineProperty(e,i.key,i)}}function _createClass(e,t,n){return t&&_defineProperties(e.prototype,t),n&&_defineProperties(e,n),e}!function(r,s){var i=function(){function i(e,t){var n=this;_classCallCheck(this,i),this.id="",this.speed=5e3,this.active=!0,this.lock=!1,this.$wrapper=t,this.id=e,this.$items=t.find(".ee--rotator-item"),1<this.$items.length&&(this.$itemsWrapper=t.find(".ee--rotator-items"),this.$nav=this.$wrapper.find(".ee--rotator-nav"),this.$prev=this.$wrapper.find(".ee--rotator-prev"),this.$next=this.$wrapper.find(".ee--rotator-next"),this.$navItems=this.$nav.find(".ee--rotator-nav-item"),this.$current=this.$items.first(),this.$itemsWrapper.css({position:"relative"}),this.$items.each(function(e,t){r(t).data("ee--rotator-index",e)}).hide(),this.$current.show(),this.interval=setInterval(function(){!0===n.active&&n.cycle()},this.$wrapper.data("ee--rotator-speed")||this.speed),this.$wrapper.on("keydown",function(e){switch(e.which){case 39:e.preventDefault(),e.stopPropagation(),n.next();break;case 37:e.preventDefault(),e.stopPropagation(),n.prev()}}).on("swiperight",function(e){e.preventDefault(),e.stopPropagation(),n.next()}).on("swipeleft",function(e){e.preventDefault(),e.stopPropagation(),n.prev()}),this.$prev.on("click",function(e){n.prev()}).on("keydown",function(e){switch(e.which){case 13:case 32:e.preventDefault(),e.stopPropagation(),n.prev()}}),this.$next.on("click",function(e){n.next()}).on("keydown",function(e){switch(e.which){case 13:case 32:e.preventDefault(),e.stopPropagation(),n.next()}}),this.$nav.length&&1<this.$navItems.length&&(this.$navItems.first().addClass("active"),this.$navItems.each(function(e,t){r(t).data("ee--rotator-index",e)}).on("click",function(e){n.goto(r(e.target).data("ee--rotator-index"))}).on("keydown",function(e){switch(e.which){case 13:case 32:e.preventDefault(),e.stopPropagation(),n.goto(r(e.target).data("ee--rotator-index"))}})),this.isLayoutBuilder()?this.buildForLayoutBuilder():this.$wrapper.data("ee--rotator-pauseonhover")&&this.$wrapper.on("mouseover",function(e){n.pause()}).on("mouseleave",function(e){n.play()}))}return _createClass(i,[{key:"buildForLayoutBuilder",value:function(){var o=this;this.$prev.length&&this.$prev.on("click",function(e){o.pause()}),this.$next.length&&this.$next.on("click",function(e){o.pause()}),this.$navItems.length&&this.$navItems.on("click",function(e){o.pause()}),r(document).on("exoComponentOps.exo.alchemist.enhancement.rotator."+this.id,function(e,t){if(s.ExoAlchemistAdmin.getActiveComponent().find(o.$wrapper).length){var n=r(t);n.find(".exo-field-op-rotator-prev").off("click").on("click",function(e){e.preventDefault(),o.prev()}),n.find(".exo-field-op-rotator-next").off("click").on("click",function(e){e.preventDefault(),o.next()})}}),r(document).on("exoComponentActive.exo.alchemist.enhancement.rotator."+this.id,function(e,t){r(t).find(o.$wrapper).length&&(o.$prev.length||o.$next.length||o.$navItems.length)&&o.pause()}),r(document).on("exoComponentInactive.exo.alchemist.enhancement.rotator."+this.id,function(e,t){r(t).find(o.$wrapper).length&&o.play()}),r(document).on("exoComponentFieldEditActive.exo.alchemist.enhancement.rotator."+this.id,function(e,t){var n=r(t);if(o.$wrapper.find(n).length){o.pause();var i=o.$items.filter(n);i.length?i.length||(i=n.find(".ee--rotator-item")):i=n.closest(".ee--rotator-item"),i.length&&i.data("ee--rotator-index")!==o.$current.data("ee--rotator-index")&&(o.cycle(i,0),s.ExoAlchemistAdmin.sizeFieldOverlay(n),s.ExoAlchemistAdmin.sizeTarget(n))}}),r(document).on("exoComponentFieldEditInactive.exo.alchemist.enhancement.rotator."+this.id,function(e,t){var n=r(t);o.$wrapper.find(n).length&&(o.$prev.length||o.$next.length||o.$navItems.length||o.play())})}},{key:"pause",value:function(){this.active=!1}},{key:"play",value:function(){this.active=!0}},{key:"prev",value:function(){this.cycle(this.getPrev())}},{key:"next",value:function(){this.cycle()}},{key:"goto",value:function(n){var i=this;this.$items.each(function(e,t){e===n&&i.cycle(r(t))})}},{key:"getNext",value:function(){var e=this.$current.next();return 0!==e.length&&e.hasClass("ee--rotator-item")||(e=this.$items.first()),e}},{key:"getPrev",value:function(){var e=this.$current.prev();return 0!==e.length&&e.hasClass("ee--rotator-item")||(e=this.$items.last()),e}},{key:"cycle",value:function(e,t){var n=this,i=this.$current.data("ee--rotator-index");t=void 0!==t?t:1e3;var o=this.$current,a=(e=e||this.getNext()).data("ee--rotator-index");!0!==this.lock&&a!==i&&(this.lock=!0,e.css({zIndex:1,position:"absolute",top:0,left:0,right:0}),this.$itemsWrapper.height(o.outerHeight()),this.$navItems.length&&(this.$navItems.removeClass("active"),r(this.$navItems.get(a)).addClass("active")),setTimeout(function(){e.show(),s.Exo.checkElementPosition(),n.$itemsWrapper.height(e.outerHeight()),o.css("z-index",2),setTimeout(function(){o.fadeOut(t,"swing",function(){n.$itemsWrapper.height(""),e.css({position:"relative"}),n.lock=!1})}),n.$current=e}))}},{key:"unload",value:function(){r(document).off("exoComponentOps.exo.alchemist.enhancement.rotator."+this.id),r(document).off("exoComponentActive.exo.alchemist.enhancement.rotator."+this.id),r(document).off("exoComponentInactive.exo.alchemist.enhancement.rotator."+this.id),r(document).off("exoComponentFieldEditActive.exo.alchemist.enhancement.rotator."+this.id),r(document).off("exoComponentFieldEditInactive.exo.alchemist.enhancement.rotator."+this.id)}},{key:"isLayoutBuilder",value:function(){return s.ExoAlchemistAdmin&&s.ExoAlchemistAdmin.isLayoutBuilder()}}]),i}();s.behaviors.exoAlchemistEnhancementRotator={count:0,instances:{},attach:function(e){var n=this;r(".ee--rotator-wrapper").once("exo.alchemist.enhancement").each(function(){var e=r(this),t=e.data("ee--rotator-id");n.instances[t+n.count]=new i(t,e),e.data("ee--rotator-count",n.count),n.count++})},detach:function(e,t,n){if("unload"===n){var i=this;r(".ee--rotator-wrapper",e).each(function(){var e=r(this),t=e.data("ee--rotator-id")+e.data("ee--rotator-count");void 0!==i.instances[t]&&(i.instances[t].unload(),delete i.instances[t])})}}}}(jQuery,Drupal);