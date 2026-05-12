/**
 * @file
 * Restore jQuery globals that pickadate 3.x relies on but jQuery 4
 * (shipped with Drupal 11.3+) removed. Pickadate is unmaintained
 * upstream, so we shim instead of forking the minified library.
 *
 * In jQuery 3.x these properties already exist with identical
 * semantics, so this file is a no-op when running on jQuery 3.x
 * (Drupal 10 and earlier Drupal 11 builds).
 */
(function () {
  'use strict';
  if (typeof jQuery === 'undefined') {
    return;
  }
  if (typeof jQuery.isArray !== 'function') {
    jQuery.isArray = Array.isArray;
  }
  if (typeof jQuery.isFunction !== 'function') {
    jQuery.isFunction = function (obj) {
      return typeof obj === 'function';
    };
  }
  if (typeof jQuery.trim !== 'function') {
    jQuery.trim = function (text) {
      return text == null ? '' : String(text).trim();
    };
  }
  if (typeof jQuery.isNumeric !== 'function') {
    jQuery.isNumeric = function (n) {
      return !isNaN(parseFloat(n)) && isFinite(n);
    };
  }
}());
