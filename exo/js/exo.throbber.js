"use strict";!function(r,a,o){a.Ajax.prototype.beforeSend=function(e,s){var t;this.$form&&(s.extraData=s.extraData||{},s.extraData.ajax_iframe_upload="1",null!==(t=r.fieldValue(this.element)))&&(s.extraData[this.element.name]=t),r(this.element).prop("disabled",!0),a.Exo.$body.addClass("ajax-loading"),this.progress&&this.progress.type&&("throbber"===this.progress.type&&o.exoLoader.alwaysFullscreen&&(this.progress.type="fullscreen"),(s="setProgressIndicator"+this.progress.type.slice(0,1).toUpperCase()+this.progress.type.slice(1).toLowerCase())in this)&&"function"==typeof this[s]&&this[s].call(this)},a.Ajax.prototype.setProgressIndicatorThrobber=function(){var e=this;this.progress.element=r('<div class="ajax-progress ajax-progress-throbber" role="status"><div class="ajax-loader">'+o.exoLoader.markup+"</div></div>"),this.progress.message&&!o.exoLoader.hideAjaxMessage&&this.progress.element.find(".ajax-loader").after('<div class="message">'+this.progress.message+"</div>"),r(this.element).after(this.progress.element),setTimeout(function(){e.progress.element.addClass("active")},10)},a.Ajax.prototype.setProgressIndicatorFullscreen=function(){var e=this;this.progress.element=r('<div class="ajax-progress ajax-progress-fullscreen" role="status">'+o.exoLoader.markup+"</div>"),r(o.exoLoader.throbberPosition).after(this.progress.element),setTimeout(function(){e.progress.element.addClass("active")},10)},a.Ajax.prototype.successOriginal=a.Ajax.prototype.success,a.Ajax.prototype.success=function(e,s){var t=this;a.Exo.$body.removeClass("ajax-loading"),this.progress.element&&this.progress.element.hasClass("active")?(this.progress.element.one(a.Exo.transitionEvent,function(){a.Ajax.prototype.successOriginal.call(t,e,s)}),this.progress.element.removeClass("active")):a.Ajax.prototype.successOriginal.call(this,e,s)}}(jQuery,Drupal,drupalSettings);