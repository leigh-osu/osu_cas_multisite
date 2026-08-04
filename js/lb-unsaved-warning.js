/**
 * @file
 * Warn before leaving a Layout Builder page with unsaved changes.
 *
 * Layout Builder parks edits in tempstore and shows a "You have unsaved
 * changes" warning, but lets navigation proceed silently. Two signals
 * mark the layout dirty:
 * - the warning message being in the DOM (covers arriving on a page with
 *   pending tempstore changes);
 * - a behaviors attach whose context contains the layout element: every
 *   mutation (block add/update/move/remove, section changes) re-renders
 *   the layout via ajax and re-attaches behaviors with it as context,
 *   while dialog opens attach only dialog content. The messages region
 *   is NOT part of those rerenders, so the message check alone misses
 *   fresh edits.
 * Submitting the layout form itself — Save, Discard, Revert are all
 * submit buttons on form.layout-builder-form — clears the guard so
 * those flows never nag. The submit listener is document-level capture
 * because the form element itself is replaced on every rerender.
 */
(function (Drupal, once) {
  'use strict';

  let ajaxDirty = false;
  let leavingViaForm = false;

  function hasUnsavedMessage() {
    return Array.prototype.some.call(
      document.querySelectorAll('.messages--warning'),
      function (el) { return /unsaved changes/i.test(el.textContent); }
    );
  }

  Drupal.behaviors.osuCasLbUnsavedWarning = {
    attach: function (context) {
      once('osu-lb-unsaved', 'body').forEach(function () {
        document.addEventListener('submit', function (e) {
          if (e.target.matches('form.layout-builder-form')) {
            leavingViaForm = true;
          }
        }, true);
        window.addEventListener('beforeunload', function (e) {
          if (!leavingViaForm && (ajaxDirty || hasUnsavedMessage())) {
            e.preventDefault();
            e.returnValue = '';
          }
        });
      });
      if (context !== document && context.nodeType === Node.ELEMENT_NODE
        && (context.matches('.layout-builder, form.layout-builder-form')
          || context.querySelector('.layout-builder'))) {
        ajaxDirty = true;
      }
    }
  };

})(Drupal, once);
