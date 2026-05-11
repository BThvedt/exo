(function ($, Drupal) {

  'use strict';

  let $scope:JQuery;
  // Module-scope reference to the most recently pressed disable-on-click
  // button. Must live outside attach() so that the press captured by an
  // attach #N mousedown is readable by the ajaxStart handler bound by
  // attach #1 (once() means the handler only ever binds once, while
  // attach can re-run as Drupal AJAX rebuilds parts of the form).
  let $disablePressed:JQuery = null;

  Drupal.behaviors.exoForm = {
    once: false,
    attach: function (context) {
      $scope = $('form.exo-form:visible');

      // Container inline has been replaced with exo-form-inline.
      // core/once replaces the removed jQuery.once that older code used —
      // necessary for Drupal 11, which no longer ships a jQuery.once
      // polyfill on core/jquery. The Drupal global `once(id, target)`
      // returns a plain Array<HTMLElement> of elements not yet marked,
      // so each one is re-wrapped in $() to keep the rest of the jQuery
      // chain working as before.
      once('exo.form.init', '.exo-form').forEach((element:HTMLElement) => {
        const $form = $(element);
        if (!$('> *:visible', $form).length) {
          // Add empty div so that we have correct margin
          $form.append('<div></div>');
        }
        $form.removeClass('is-disabled');
        $form.find('.container-inline').removeClass('container-inline');
        $form.find('.form--inline').removeClass('form--inline').addClass('exo-form-inline');
        $form.find('.form-items-inline').removeClass('form-items-inline');
      });

      // Disable on click.
      $('.exo-form.is-disabled').each((index, element) => {
        $(element).removeClass('is-disabled');
      });
      $('.exo-form-button-disabled-clone').each((index, element) => {
        $(element).remove();
      });
      $('.exo-form-button-displayed-has-clone').each((index, element) => {
        $(element).removeClass('exo-form-button-displayed-has-clone').show();
      });
      const disableButton = ($button) => {
        const $form = $button.closest('form.exo-form');
        const message = $button.data('exo-form-button-disable-message');
        const $clone = $button.clone().css({
          minWidth: $button.outerWidth() + 'px',
          textAlign: 'center',
        }).addClass('exo-form-button-disabled-clone is-disabled').insertAfter($button);
        if (message) {
          if ($clone.is('input')) {
            $clone.val(message);
          }
          else {
            $clone.text(message);
          }
        }
        $button.addClass('exo-form-button-displayed-has-clone').hide();
        if ($button.data('exo-form-button-disable-form')) {
          $form.addClass('is-disabled');
        }
      };
      // Per-button: mark each disable-on-click button once so the
      // capture-press handlers don't accumulate across re-attaches.
      // pointerdown/keydown both populate the shared module-scope
      // $disablePressed so the ajaxStart / submit handlers below can
      // read the same value regardless of which attach captured it.
      once('exo.form.disable.btn', '.exo-form-button-disable-on-click', context).forEach((el:HTMLElement) => {
        const $el = $(el);
        const capture = () => {
          // Use the bound button (not e.target) so child icons or text
          // nodes don't get cloned in place of the button itself.
          $disablePressed = $el;
        };
        // pointerdown covers mouse, touch, and pen. keydown covers
        // keyboard activation via Enter or Space which doesn't fire
        // mousedown/pointerdown.
        $el.on('pointerdown.exo.form.disable', capture);
        $el.on('keydown.exo.form.disable', e => {
          if (e.which === 13 || e.which === 32) {
            capture();
          }
        });
      });

      let $disableButtons = $('.exo-form-button-disable-on-click', context);
      if ($disableButtons.length) {
        const $form = $disableButtons.closest('form.exo-form');
        // Use the server-rendered `.use-ajax` class instead of probing
        // for the `data-once="drupal-ajax"` marker. The marker is set by
        // the drupal.ajax behavior's own attach() — and Drupal behaviors
        // run in alphabetical order, so by the time exoForm attaches,
        // drupal.ajax may not have stamped buttons yet, producing a
        // race that silently skipped the ajaxStart binding.
        if ($disableButtons.filter('.use-ajax').length) {
          // Pass the <html> element rather than `document` because
          // core/once requires an Element target — `document` itself
          // silently returns an empty array, which would skip the
          // ajaxStart binding entirely. Use `.on` (not `.one`) so the
          // binding survives across multiple AJAX cycles in the same
          // page lifetime; we use $disablePressed presence to decide
          // whether to act.
          once('exo.form.disable', document.documentElement).forEach(() => {
            $(document).on('ajaxStart.exo.form.disable', () => {
              setTimeout(() => {
                if ($disablePressed && $disablePressed.length) {
                  disableButton($disablePressed);
                  $disablePressed = null;
                }
              });
            });
          });
        }
        // $form may be a multi-element jQuery; pass the underlying nodes
        // so each form is marked individually.
        once('exo.form.disable', $form.get()).forEach((element:HTMLElement) => {
          $(element).on('submit', () => {
            if ($disablePressed && $disablePressed.length) {
              disableButton($disablePressed);
              $disablePressed = null;
            }
          });
        });
      }

      // Form styling.
      once('exo.form.td.compact', 'td .dropbutton-wrapper', context).forEach((element:HTMLElement) => {
        setTimeout(() => {
          $(element).css('min-width', $(element).outerWidth());
        });
        $(element).parent().addClass('exo-form-table-compact');
      });
      once('exo.form.td.compact', 'td.views-field-changed, td.views-field-created', context).forEach((element:HTMLElement) => {
        $(element).addClass('exo-form-table-compact');
      });
      once('exo.form.td.compact', 'td > .exo-icon', context).forEach((element:HTMLElement) => {
        const $td = $(element).parent();
        if ($td.children(':not(.exo-icon-label)').length === 1) {
          $td.addClass('exo-form-table-compact');
        }
      });

      // Webform support.
      once('exo.form.refresh', '.webform-tabs', context).forEach((element:HTMLElement) => {
        const $tabs = $(element);
        $tabs.addClass('horizontal-tabs').wrap('<div class="exo-form-horizontal-tabs exo-form-element exo-form-element-js" />');
        $tabs.find('.item-list ul').addClass('horizontal-tabs-list').find('> li').addClass('horizontal-tab-button');
        $tabs.find('> .webform-tab').addClass('horizontal-tabs-pane').wrapAll('<div class="horizontal-tabs-panes" />');
        $tabs.on('tabsbeforeactivate', function (e, ui) {
          ui.oldPanel.hide();
          ui.newPanel.show();
        });
      });

      // Hide empty containers.
      $(context).find('.exo-form-container-hide').each(function () {
        if ($(this).text().trim().length) {
          $(this).removeClass('exo-form-container-hide');
        }
      });

      // Process each exo form.
      once('exo.form', $scope.get()).forEach((element:HTMLElement) => {
        const $localscope = $(element);
        // Wrap pad.
        $localscope.filter('.exo-form-wrap').each((index, el) => {
          if ($(el).html().trim()[0] !== '<') {
            $(el).addClass('exo-form-wrap-pad');
          }
        });
        // Support utilization of a parent which defines an exo theme.
        const $parentTheme = $localscope.closest('[data-exo-theme]');
        if ($parentTheme.length) {
          $localscope.removeClass(function (index, className) {
            return (className.match (/(^|\s)exo-form-theme-\S+/g) || []).join(' ');
          }).addClass('exo-form-theme-' + $parentTheme.data('exo-theme'));
        }
      });

      // Perform only once.
      if (!this.once) {
        this.once = true;
        function watchInline() {
          $scope.find('.exo-form-inline').each((index, element) => {
            const $element = $(element);
            let innerWidth = 0;
            $element.removeClass('exo-form-inline-stack');
            $element.find('> *:visible').each((index, el) => {
              innerWidth += $(el).outerWidth();
            });
            if (innerWidth > Drupal.Exo.$window.width()) {
              $element.addClass('exo-form-inline-stack');
            }
          });

          $(context).find('table').each((index, element) => {
            const $table = $(element);
            if (!$table.closest('form.exo-form').length) {
              $table.addClass('exo-form-table-wrap');
            }
            const $parent = $table.parent();
            if ($table.outerWidth() > $parent.outerWidth() + 2) {
              if (!$parent.hasClass('exo-form-table-overflow')) {
                $table.wrap('<div class="exo-form-table-overflow" />');
              }
            }
            else {
              if ($parent.hasClass('exo-form-table-overflow')) {
                $table.unwrap('.exo-form-table-overflow');
              }
            }
          });
        }

        Drupal.Exo.addOnResize('exo.form.core', watchInline);
        Drupal.Exo.event('ready').on('exo.form', e => {
          Drupal.Exo.event('ready').off('exo.form');
          watchInline();
        });
      }
    }
  };

})(jQuery, Drupal);
