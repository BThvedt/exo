"use strict";!function(t,e){e.behaviors.exoFormAutogrow={attach:function(o){var a=t(o).find("textarea[data-autogrow]").once("exo.form.autogrow");a.length&&e.Exo.event("ready").on("exo.form.autogrow",function(){a.each(function(){var o=t(this),a=o.data("autogrow-max");a&&o.css("max-height",a)}),autosize(a)})}}}(jQuery,Drupal,Drupal.displace);