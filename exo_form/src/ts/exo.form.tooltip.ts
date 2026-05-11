(function ($, Drupal, displace) {

  'use strict';

  const $wrapper = $('<div class="exo-tooltip-wrapper"></div>');
  const $inner = $('<div class="exo-tooltip-inner"></div>').appendTo($wrapper);
  let lock = false;
  let timeout;
  $inner.on('mouseenter', e => {
    lock = true;
  });
  $inner.on('mouseleave', e => {
    lock = false;
  });
  $wrapper.appendTo(Drupal.Exo.$exoCanvas);

  Drupal.behaviors.exoFormTooltip = {
    attach: function (context) {

      // core/once replaces the jQuery.once removed in Drupal 11.
      once('exo.form.tooltip', '.exo-tooltip', context).forEach((element:HTMLElement) => {
        const $tooltip = $(element);
        const $trigger = $tooltip.find('.exo-tooltip-trigger');
        const $content = $tooltip.find('.exo-tooltip-content');
        clearTimeout(timeout);
        $trigger.on('click', e => {
          const $element = $tooltip.closest('.exo-form-element');
          const offset = $trigger.offset();
          $inner.html($content.html()).css({
            transform: '',
          });
          $wrapper.attr('class', $tooltip.closest('form').attr('class')).addClass('exo-tooltip-wrapper').css({
            top: offset.top - displace.offsets.top,
            left: offset.left - displace.offsets.left + ($trigger.outerWidth() / 2),
          }).addClass('active');

          const elementOffset = $element.offset();
          const innerOffset = $inner.offset();
          if (elementOffset.left > innerOffset.left) {
            $inner.css({
              transform: 'translateX(' + (elementOffset.left - innerOffset.left) + 'px)',
            });
          }
          const right = $inner.offset().left + $inner.outerWidth();
          if (right > Drupal.Exo.$window.width()) {
            $inner.css({
              transform: 'translateX(' + (elementOffset.left - innerOffset.left - (right - Drupal.Exo.$window.width())) + 'px)',
            });
          }
        });
        $trigger.on('mouseleave', e => {
          const watch = function () {
            clearTimeout(timeout);
            if (lock === false) {
              $wrapper.removeClass('active');
            }
            else {
              timeout = setTimeout(watch, 500);
            }
          }
          timeout = setTimeout(watch, 500);
        });
      });

      // // Tooltip support.
      // $(context).find('.exo-tooltip').once('exo.form.tooltip').each((index, element) => {
      //   const $tooltip = $(element);
      //   const $trigger = $tooltip.find('.exo-tooltip-trigger');
      //   const $content = $tooltip.find('.exo-tooltip-content');
      //   let timeout;
      //   let lock = false;
      //   $trigger.on('click', e => {
      //     clearTimeout(timeout);
      //     const isActive = $tooltip.hasClass('active')
      //     const $element = $tooltip.closest('.exo-form-element');
      //     $('.exo-tooltip.active').not($tooltip).removeClass('active');
      //     if (isActive) {
      //       $tooltip.removeClass('active');
      //     }
      //     else {
      //       $content.css({
      //         transform: '',
      //       });
      //       const elementOffset = $element.offset();
      //       const contentOffset = $content.offset();
      //       if (elementOffset.left > contentOffset.left) {
      //         $content.css({
      //           transform: 'translateX(' + (elementOffset.left - contentOffset.left) + 'px)',
      //         });
      //       }
      //       $tooltip.addClass('active');
      //     }
      //   });
      //   $content.on('mouseenter', e => {
      //     lock = true;
      //   });
      //   $content.on('mouseleave', e => {
      //     lock = false;
      //   });
      //   $trigger.on('mouseleave', e => {
      //     const watch = function () {
      //       if (lock === false) {
      //         $tooltip.removeClass('active');
      //       }
      //       else {
      //         timeout = setTimeout(watch, 200);
      //       }
      //     }
      //     timeout = setTimeout(watch, 200);
      //   });
      // });
    }
  };

})(jQuery, Drupal, Drupal.displace);
