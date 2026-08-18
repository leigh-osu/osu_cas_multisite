(function () {
  // Registered under the upstream plugin id so the existing toolbar button and
  // any stored editor configuration keep working; only the dialog changes.
  CKEDITOR.plugins.add("osu_ckeditor_plugins_osu_buttons", {
    icons: "osu_buttons",
    init: function (editor) {
      // Deliberately NOT appendStyleSheet(manzanita.css) here. That loads the
      // theme into the whole admin document, which restyled Claro's tables --
      // dark table headers on every node edit form. The dialog renders its
      // preview in an isolated iframe instead, so manzanita styles the button
      // and nothing else. The path travels in config for the dialog to use.

      editor.addCommand(
        "osu_buttons",
        new CKEDITOR.dialogCommand("osu_buttonsDialog")
      );
      editor.ui.addButton("osu_buttons", {
        label: "Button Picker",
        command: "osu_buttons",
        toolbar: "osu_buttons"
      });

      CKEDITOR.dialog.add(
        "osu_buttonsDialog",
        this.path + "dialogs/osu_buttons.js"
      );
    }
  });
})();
