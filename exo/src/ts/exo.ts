/**
 * @file
 * Global eXo javascript.
 */

TSinclude('./exo/_exo.manager.ts')
TSinclude('./exo/_exo.collection.ts')
TSinclude('./exo/_exo.data.ts')
TSinclude('./exo/_exo.data.manager.ts')
TSinclude('./exo/_exo.data.collection.ts')
TSinclude('./exo/_exo.event.ts')

(function ($, _, Drupal, displace) {
  TSinclude('./exo/_exo.ts')
  TSinclude('./exo/_exo.displace.ts')

  Drupal.behaviors.exo = {};
  document.body.style.position = 'relative';

  // Prevent default anchor behavior.
  let hash = window.location.hash;
  if (typeof hash === 'string' && hash.length) {
    hash = hash.replace('#', '');
    document.addEventListener("DOMContentLoaded", function () {
      let $anchor = $('a[name="' + hash + '"]');
      if (!$anchor.length) {
        $anchor = $('#' + hash);
      }
      if ($anchor.length) {
        window.scrollTo(0, 0);
        Drupal.Exo.event('finished').on('exo.hash', () => {
          const scrollMarginTop = parseFloat($anchor.css('scroll-margin-top')) || 0;
          const scrollPaddingTop = parseFloat($(document.scrollingElement || document.documentElement).css('scroll-padding-top')) || 0;
          $('html, body').animate({
            scrollTop: $anchor.offset().top - scrollMarginTop - scrollPaddingTop - 20,
          }, 500);
          Drupal.Exo.event('finished').off('exo.hash');
        });
      }
    });
  }

  // Maximum time allotted for loading.
  let loadTimeout = setTimeout(() => {
    Drupal.Exo.init(document.body);
  }, 1000);

  // Support loadCSS used by the advagg module.
  if (typeof loadCSS !== 'undefined') {
    Drupal.Exo.debug('log', 'Exo', 'Found loadCSS');

    // Wait till stylesheets are loaded.
    const domain = (url) => {
      return url.replace('http://', '').replace('https://', '').split('/')[0];
    }
    const $sheets = $('link[rel="stylesheet"]').filter((id, element) => {
      return location.href && element.href ? domain(location.href) === domain(element.href) : false;
    });

    if ($sheets.length) {
      Drupal.Exo.debug('log', 'Exo', 'Sheets to Load', $sheets);
      let count = 0;
      $sheets.each((id, element) => {
        const href = $(element).prop('href');
        Drupal.Exo.debug('log', 'Exo', 'Load', href);
        onloadCSS(element, function() {
          count++;
          if (count == $sheets.length) {
            clearTimeout(loadTimeout);
            Drupal.Exo.init(document.body);
          }
        });
      });
    }
    else {
      clearTimeout(loadTimeout);
      Drupal.Exo.init(document.body);
    }
  }
  else {
    clearTimeout(loadTimeout);
    Drupal.behaviors.exo.attach = function (context) {
      Drupal.Exo.init(document.body);

      Drupal.behaviors.exo.attach = function (context) {
        Drupal.Exo.attach(document.body);
      }
    }
  }

  // When CSS has been loaded.
  function onloadCSS( ss, callback ) {
    var called;
    function newcb(){
        if( !called && callback ){
          called = true;
          callback.call( ss );
        }
    }
    if( ss.addEventListener ){
      ss.addEventListener( "load", newcb );
    }
    if( ss.attachEvent ){
      ss.attachEvent( "onload", newcb );
    }
    if( "isApplicationInstalled" in navigator && "onloadcssdefined" in ss ) {
      ss.onloadcssdefined( newcb );
    }
  }

  Drupal.behaviors.exo.detach = function (context, settings, trigger) {
    if (trigger === 'unload') {
      Drupal.Exo.cleanElementPosition(context);
    }
  }

})(jQuery, _, Drupal, Drupal.displace);
