(function ($) {
  /**
   * The CAS button picker.
   *
   * Two schemes and one size, matching what the site actually uses, and able to
   * edit a button rather than only insert one.
   */
  CKEDITOR.dialog.add("osu_buttonsDialog", function (editor) {
    // The dialog factory is handed its editor, so use that rather than
    // CKEDITOR.currentInstance, which is only set while an editor has focus and
    // is undefined when the dialog definition is first evaluated.
    var editorId = editor.name;

    var SCHEMES = ["cas-button-light", "cas-button-dark"];
    var DEFAULT_SCHEME = "cas-button-dark";

    var buttonText = "Button Text";
    var buttonScheme = DEFAULT_SCHEME;
    var buttonLink = "";
    var buttonLinkTarget = "";
    // Markup that sits inside the anchor alongside the label, typically an
    // icon element. Preserved verbatim so editing a button does not strip it.
    var buttonHtml = "";
    // The anchor being edited, or null when inserting a new one.
    var editing = null;

    function classesOf(el) {
      return (el.getAttribute("class") || "").split(/\s+/).filter(Boolean);
    }

    /**
     * The nearest ancestor that is a CAS button, or null.
     */
    function findButton(element) {
      var node = element;
      while (node && node.type === CKEDITOR.NODE_ELEMENT) {
        if (node.getName() === "a" && node.hasClass("btn")) {
          return node;
        }
        node = node.getParent();
      }
      return null;
    }

    function markup() {
      var classes = ["btn", buttonScheme].join(" ");
      return classes;
    }

    /**
     * Renders the preview inside an iframe carrying manzanita's stylesheet.
     *
     * The obvious approach -- appending manzanita.css to the page and dropping a
     * <button> in the dialog -- has two faults. It restyles the whole admin UI
     * (it turned Claro's table headers dark on every edit form), and the preview
     * still lies, because the admin theme's own rules override manzanita's
     * custom properties. An iframe gets exactly the front end's cascade and
     * leaks nothing back.
     */
    function preview() {
      var frame = document.getElementById("cas-button-preview-" + editorId);
      if (!frame) {
        return;
      }
      var css = editor.config.osuCasButtonPreviewCss || [];
      if (typeof css === "string") {
        css = [css];
      }
      var links = css.map(function (href) {
        return '<link rel="stylesheet" href="' + href + '">';
      }).join("");
      var label = (buttonHtml || "") + $("<div>").text(buttonText).html();
      frame.srcdoc =
        "<!doctype html><html><head>" + links +
        "<style>body{margin:0;padding:12px;display:flex;justify-content:center;" +
        "align-items:center;background:transparent;}</style>" +
        "</head><body>" +
        '<a class="' + markup() + '" href="#" onclick="return false">' + label + "</a>" +
        "</body></html>";
    }

    function selectSwatch(scheme) {
      buttonScheme = scheme;
      $("#" + editorId + " .cas-scheme").removeClass("scheme-active");
      $("#" + editorId + ' .cas-scheme[data-scheme="' + scheme + '"]').addClass("scheme-active");
      preview();
    }

    function loadEvents() {
      var element = editor.getSelection().getStartElement();
      editing = element ? findButton(element) : null;

      if (editing) {
        // Editing: take everything from the button, including its scheme --
        // upstream read the text and link but not the class, so confirming the
        // dialog silently repainted the button with the default colour.
        var raw = editing.$;
        buttonText = raw.textContent.trim();
        var escaped = buttonText.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
        buttonHtml = raw.innerHTML.replace(new RegExp(escaped, "im"), "").trim();
        buttonLink = editing.getAttribute("href") || "";
        buttonLinkTarget = editing.getAttribute("target") || "";
        var found = classesOf(editing).filter(function (c) {
          return SCHEMES.indexOf(c) !== -1;
        });
        buttonScheme = found.length ? found[0] : DEFAULT_SCHEME;
      }
      else {
        buttonText = "Button Text";
        buttonHtml = "";
        buttonLink = "";
        buttonLinkTarget = "";
        buttonScheme = DEFAULT_SCHEME;
      }

      $("#" + editorId + " #button-text").val(buttonText);
      $("#" + editorId + " #button-link").val(buttonLink);
      $("#" + editorId + " #button-new-window").prop("checked", buttonLinkTarget === "_blank");
      $("#" + editorId + " .cas-dialog-mode").text(
        editing ? "Editing an existing button" : "Adding a button"
      );

      $("#" + editorId + " #button-text").off("keyup").on("keyup", function () {
        buttonText = $(this).val();
        preview();
      });
      $("#" + editorId + " #button-link").off("keyup").on("keyup", function () {
        buttonLink = $(this).val();
      });
      $("#" + editorId + " #button-new-window").off("change").on("change", function () {
        buttonLinkTarget = $(this).is(":checked") ? "_blank" : "";
      });
      $("#" + editorId + " .cas-scheme").off("click").on("click", function () {
        selectSwatch($(this).data("scheme"));
      });

      selectSwatch(buttonScheme);
    }

    var form =
      '<form id="' + editorId + '" class="osu-buttons cas-buttons">' +
        '<p class="cas-dialog-mode"></p>' +
        '<iframe id="cas-button-preview-' + editorId + '" title="Button preview" ' +
          'style="width:100%;height:76px;border:0;overflow:hidden" scrolling="no"></iframe>' +
        '<div class="button-text">' +
          "<label>Button text</label><br/>" +
          '<input id="button-text" type="text" placeholder="Button Text">' +
        "</div>" +
        '<div class="button-link">' +
          "<label>Link</label><br/>" +
          '<input id="button-link" type="text" placeholder="https://…  or  /a/path">' +
          '<br/><label><input id="button-new-window" type="checkbox"> Open in a new window</label>' +
        "</div>" +
        '<div class="cas-schemes">' +
          "<label>Style</label><br/>" +
          '<button type="button" class="cas-scheme" data-scheme="cas-button-light" ' +
            'style="background:#f7f5f5;color:#d73f09;border:2px solid #ffb500;' +
            'text-transform:uppercase;letter-spacing:.05em;padding:4px 12px;margin-right:8px">Light</button>' +
          '<button type="button" class="cas-scheme" data-scheme="cas-button-dark" ' +
            'style="background:#d73f09;color:#f7f5f5;border:2px solid #ffb500;' +
            'text-transform:uppercase;letter-spacing:.05em;padding:4px 12px">Dark</button>' +
        "</div>" +
      "</form>";

    return {
      title: "CAS Button",
      minWidth: 480,
      minHeight: 300,
      contents: [
        {
          id: "tab-basic",
          label: "Basic Settings",
          elements: [
            {
              type: "html",
              html: form,
              onLoad: loadEvents
            }
          ]
        }
      ],
      onShow: function () {
        loadEvents();
      },
      onOk: function () {
        var classes = markup();
        var label = (buttonHtml || "") + $("<div>").text(buttonText).html();

        if (editing) {
          // Update in place. Upstream removed the element and inserted a
          // replacement, which discarded anything else on it and moved the
          // cursor; keeping the element preserves id, rel, data attributes and
          // the surrounding selection.
          editing.setAttribute("class", classes);
          editing.setAttribute("href", buttonLink);
          if (buttonLinkTarget) {
            editing.setAttribute("target", buttonLinkTarget);
          }
          else {
            editing.removeAttribute("target");
          }
          editing.setHtml(label);
          editor.fire("change");
          return;
        }

        var target = buttonLinkTarget ? ' target="' + buttonLinkTarget + '"' : "";
        editor.insertHtml(
          '<a class="' + classes + '" href="' + buttonLink + '"' + target + ">" + label + "</a>",
          "unfiltered_html"
        );
      }
    };
  });
})(jQuery);
