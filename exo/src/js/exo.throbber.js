/* global Promise */
(function ($, window, Drupal, drupalSettings) {

  'use strict';

  /**
   * Wrap Drupal.Ajax.prototype.beforeSend rather than replacing it.
   *
   * Drupal 11 core's beforeSend handles form value serialization,
   * disabling the trigger element, and dispatching to the right
   * setProgressIndicator* method. We only want to add two things:
   *   - flag the body so global styling can react ('ajax-loading')
   *   - rewrite a 'throbber' progress type to 'fullscreen' when the
   *     site asks for an always-fullscreen loader
   * Wrapping (instead of copying the entire core function) means we
   * automatically inherit any future core changes (focus tracking,
   * accessibility refinements, etc.) without manual re-sync.
   */
  var beforeSendOriginal = Drupal.Ajax.prototype.beforeSend;
  Drupal.Ajax.prototype.beforeSend = function (xmlhttprequest, options) {
    Drupal.Exo.$body.addClass('ajax-loading');

    // Always show throbber as fullscreen overlay when configured to do so.
    if (
      this.progress
      && this.progress.type === 'throbber'
      && drupalSettings.exoLoader
      && drupalSettings.exoLoader.alwaysFullscreen
    ) {
      this.progress.type = 'fullscreen';
    }

    return beforeSendOriginal.call(this, xmlhttprequest, options);
  };

  /**
   * Overrides the throbber progress indicator.
   */
  Drupal.Ajax.prototype.setProgressIndicatorThrobber = function () {
    var _this = this;
    this.progress.element = $('<div class="ajax-progress ajax-progress-throbber" role="status"><div class="ajax-loader">' + drupalSettings.exoLoader.markup + '</div></div>');
    if (this.progress.message && !drupalSettings.exoLoader.hideAjaxMessage) {
      this.progress.element.find('.ajax-loader').after('<div class="message">' + this.progress.message + '</div>');
    }
    $(this.element).after(this.progress.element);
    setTimeout(function () {
      _this.progress.element.addClass('active');
    }, 10);
  };

  /**
   * Sets the fullscreen progress indicator.
   *
   * throbberPosition is configurable and may point at either:
   *   - a container the loader should live inside of (body, html, or
   *     any wrapper element), or
   *   - a sibling element the loader should be inserted after.
   * For <body>/<html> targets, .after() would put the loader between
   * </body> and </html> — invalid placement that browsers render
   * with no offsetParent. Append into container-like targets instead
   * so the loader becomes a real child of the element.
   */
  Drupal.Ajax.prototype.setProgressIndicatorFullscreen = function () {
    var _this = this;
    this.progress.element = $('<div class="ajax-progress ajax-progress-fullscreen" role="status">' + drupalSettings.exoLoader.markup + '</div>');
    var $target = $(drupalSettings.exoLoader.throbberPosition);
    if ($target.is('body, html')) {
      $target.append(this.progress.element);
    }
    else {
      $target.after(this.progress.element);
    }
    setTimeout(function () {
      _this.progress.element.addClass('active');
    }, 10);
  };

  /**
   * Transition out and then run core's success handler.
   *
   * Drupal 11 changed Drupal.Ajax.prototype.success to return a Promise
   * (from commandExecutionQueue), and callers — including core's own
   * post-command logic that re-attaches behaviors on the form — chain
   * off that Promise. Our wrapper MUST return that Promise (or one
   * chained off it) or the post-command logic never runs.
   *
   * When a throbber is currently active we delay invoking core's
   * success until the transition-out finishes, so the loader animates
   * away before commands like ajaxReplace swap in new content. A
   * setTimeout fallback ensures we always resolve even if the
   * browser doesn't fire transitionend (e.g. element removed early or
   * no actual transition declared on it).
   */
  var successOriginal = Drupal.Ajax.prototype.success;
  Drupal.Ajax.prototype.success = function (response, status) {
    var _this = this;
    Drupal.Exo.$body.removeClass('ajax-loading');

    if (this.progress && this.progress.element && this.progress.element.hasClass('active')) {
      var transitionDone = new Promise(function (resolve) {
        var done = false;
        var finish = function () {
          if (done) {
            return;
          }
          done = true;
          resolve();
        };
        _this.progress.element.one(Drupal.Exo.transitionEvent, finish);
        // Fallback for browsers/elements where transitionend never fires.
        setTimeout(finish, 500);
      });
      this.progress.element.removeClass('active');
      return transitionDone.then(function () {
        return successOriginal.call(_this, response, status);
      });
    }

    return successOriginal.call(this, response, status);
  };

})(jQuery, this, Drupal, drupalSettings);
