"use strict";!function(n,a){var i;a.behaviors.exoForm={once:!1,attach:function(e){i=n("form.exo-form:visible"),n(".exo-form").once("exo.form.init").each(function(e,o){o=n(o);n("> *:visible",o).length||o.append("<div></div>"),o.removeClass("is-disabled"),o.find(".container-inline").removeClass("container-inline"),o.find(".form--inline").removeClass("form--inline").addClass("exo-form-inline"),o.find(".form-items-inline").removeClass("form-items-inline")}),n(".exo-form.is-disabled").each(function(e,o){n(o).removeClass("is-disabled")}),n(".exo-form-button-disabled-clone").each(function(e,o){n(o).remove()}),n(".exo-form-button-displayed-has-clone").each(function(e,o){n(o).removeClass("exo-form-button-displayed-has-clone").show()});function t(e){var o=(e=n(e.target)).closest("form.exo-form");e.data("exo-form-button-disable-message"),e.clone().css({minWidth:e.outerWidth()+"px",textAlign:"center"}).addClass("exo-form-button-disabled-clone is-disabled").insertAfter(e),e.addClass("exo-form-button-displayed-has-clone").hide(),e.data("exo-form-button-disable-form")&&setTimeout(function(){o.addClass("is-disabled")},100)}var o;n(".exo-form-button-disable-on-click",e).once("exo.form.disable").on("mousedown",function(e){var o=n(e.target);setTimeout(function(){o.hasClass("exo-form-button-displayed-has-clone")||t(e)},100)}).on("click",t),n(e).find("td .dropbutton-wrapper").once("exo.form.td.compact").each(function(e,o){setTimeout(function(){n(o).css("min-width",n(o).outerWidth())})}).parent().addClass("exo-form-table-compact"),n(e).find("td.views-field-changed, td.views-field-created").once("exo.form.td.compact").addClass("exo-form-table-compact"),n(e).find("td > .exo-icon").once("exo.form.td.compact").each(function(e,o){o=n(o).parent();1===o.children(":not(.exo-icon-label)").length&&o.addClass("exo-form-table-compact")}),n(e).find("table").once("exo.form.table").each(function(e,o){o=n(o);o.closest("form.exo-form").length||o.addClass("exo-form-table-wrap"),o.outerWidth()>o.parent().outerWidth()+2&&o.wrap('<div class="exo-form-table-overflow" />')}),n(e).find(".webform-tabs").once("exo.form.refresh").each(function(e){n(this).addClass("horizontal-tabs").wrap('<div class="exo-form-horizontal-tabs exo-form-element exo-form-element-js" />'),n(this).find(".item-list ul").addClass("horizontal-tabs-list").find("> li").addClass("horizontal-tab-button"),n(this).find("> .webform-tab").addClass("horizontal-tabs-pane").wrapAll('<div class="horizontal-tabs-panes" />')}).on("tabsbeforeactivate",function(e,o){o.oldPanel.hide(),o.newPanel.show()}),n(e).find(".exo-form-container-hide").each(function(){n(this).text().trim().length&&n(this).removeClass("exo-form-container-hide")}),i.once("exo.form").each(function(e,o){var o=n(o),t=(o.filter(".exo-form-wrap").each(function(e,o){"<"!==n(o).html().trim()[0]&&n(o).addClass("exo-form-wrap-pad")}),o.closest("[data-exo-theme]"));t.length&&o.removeClass(function(e,o){return(o.match(/(^|\s)exo-form-theme-\S+/g)||[]).join(" ")}).addClass("exo-form-theme-"+t.data("exo-theme"))}),this.once||(o=function(){i.find(".exo-form-inline").each(function(e,o){var o=n(o),t=0;o.removeClass("exo-form-inline-stack"),o.find("> *:visible").each(function(e,o){t+=n(o).outerWidth()}),t>a.Exo.$window.width()&&o.addClass("exo-form-inline-stack")})},this.once=!0,a.Exo.addOnResize("exo.form.core",o),a.Exo.event("ready").on("exo.form",function(e){a.Exo.event("ready").off("exo.form"),o()}))}}}(jQuery,Drupal);