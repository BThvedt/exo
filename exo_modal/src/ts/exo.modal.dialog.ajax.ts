(function ($, Drupal) {

  if (Drupal.AjaxCommands.prototype.closeDialog) {
    Drupal.AjaxCommands.prototype.closeDrupalDialog = Drupal.AjaxCommands.prototype.closeDialog;
  }
  Drupal.AjaxCommands.prototype.closeDialog = function (ajax, response, status) {
    var $dialog = $(response.selector);
    if (Drupal.AjaxCommands.prototype.closeDrupalDialog && $dialog.length && $dialog.is(':ui-dialog')) {
      Drupal.AjaxCommands.prototype.closeDrupalDialog(ajax, response, status);
    }
    else if (Drupal.ExoModal) {
      // Support eXo modals. Drupal wants to use its default modals, but when
      // eXo modals are used in it's place, many modules will still call this
      // command to close the "dialog" it thinks is open.
      Drupal.AjaxCommands.prototype.exoModalDialogClose(ajax, response, status);
    }
  };

  if (Drupal.AjaxCommands.prototype.setDialogOption) {
    Drupal.AjaxCommands.prototype.setDrupalDialogOption = Drupal.AjaxCommands.prototype.setDialogOption;
  }
  Drupal.AjaxCommands.prototype.setDialogOption = function (ajax, response, status) {
    var $dialog = $(response.selector);
    if (Drupal.AjaxCommands.prototype.setDrupalDialogOption && $dialog.length && $dialog.is(':ui-dialog')) {
      Drupal.AjaxCommands.prototype.setDrupalDialogOption(ajax, response, status);
    }
    else if (Drupal.ExoModal) {
      // Support eXo modals. Drupal wants to use its default modals, but when
      // eXo modals are used in it's place, many modules will still call this
      // command to change the options of the "dialog" it thinks is open.
      var modals = Drupal.ExoModal.getVisible();
      if (modals.count()) {
        modals.getLast().set(response.optionName, response.optionValue);
      }
    }
  };

  Drupal.AjaxCommands.prototype.exoModalDialogClose = function (ajax, response, status) {
    if (Drupal.ExoModal) {
      var modals = Drupal.ExoModal.getVisible();
      if (modals.count()) {
        const modal = modals.getLast();
        $(window).trigger('dialog:beforeclose', [modal, modal.getElement()]);
        modal.close();
        $(window).trigger('dialog:afterclose', [modal, modal.getElement()]);
      }
    }
  }

  /**
   * Ajax command to open URL in a modal dialog.
   *
   * @param {Drupal.Ajax} [ajax]
   *   An Ajax object.
   * @param {object} response
   *   The Ajax response.
   */
  Drupal.AjaxCommands.prototype.openModalDialogWithUrl = function (
    ajax,
    response,
  ) {
    const dialogOptions = response.dialogOptions || {};
    const elementSettings = {
      progress: { type: 'throbber' },
      dialogType: 'modal',
      dialog: dialogOptions,
      url: response.url,
      httpMethod: 'GET',
    };
    Drupal.ajax(elementSettings).execute();
  };

})(jQuery, Drupal);
