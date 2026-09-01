/**
 * @file
 * Let CKEditor 4 dialogs work inside jQuery UI modals.
 *
 * CKEditor 4 appends its dialogs and dropdown panels to <body>, outside any
 * jQuery UI dialog. When an editor sits in a modal (layout_builder_modal's
 * block forms), jQuery UI's modal focus trap sees focus landing "outside"
 * and yanks it back, so the CKEditor dialog's text and link fields cannot be
 * typed in. Node forms are plain pages, which is why they are unaffected.
 *
 * The documented escape hatch is _allowInteraction: teach it that CKEditor's
 * floating UI belongs to the modal.
 */

(($) => {
  if ($.ui && $.ui.dialog) {
    const allowInteraction = $.ui.dialog.prototype._allowInteraction;
    $.ui.dialog.prototype._allowInteraction = function (event) {
      if (
        event.target instanceof Element &&
        event.target.closest('.cke_dialog, .cke_panel')
      ) {
        return true;
      }
      return allowInteraction
        ? allowInteraction.apply(this, arguments)
        : true;
    };
  }
})(jQuery);
