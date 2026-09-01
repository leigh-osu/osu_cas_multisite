<?php

namespace Drupal\osu_cas_multisite_editor\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\editor\Entity\Editor;

/**
 * Enables the CKEditor 4 indentblock plugin.
 *
 * Drupal's optimized CKEditor build ships the Indent/Outdent buttons with
 * only indentlist behind them, so outside a list they sit greyed out — on
 * the D7 site the full package included indentblock and paragraph indent
 * worked. This loads the matching addon (libraries/indentblock, pinned to
 * the bundled build's version) whenever a toolbar carries either button.
 *
 * Indentation is class-based (indent-1..indent-3), not inline margins:
 * the classes survive Basic HTML's attribute filtering, and manzanita
 * styles them. Buttonless, so it registers contextually rather than via
 * getButtons().
 *
 * @CKEditorPlugin(
 *   id = "indentblock",
 *   label = @Translation("Indent blocks"),
 *   module = "osu_cas_multisite_editor"
 * )
 */
class IndentBlock extends CKEditorPluginBase implements CKEditorPluginContextualInterface {

  /**
   * The indent classes, level 1 to 3.
   */
  const INDENT_CLASSES = ['indent-1', 'indent-2', 'indent-3'];

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return 'libraries/indentblock/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
    if (!$editor->hasAssociatedFilterFormat()) {
      return FALSE;
    }
    $settings = $editor->getSettings();
    foreach ($settings['toolbar']['rows'] ?? [] as $row) {
      foreach ($row as $group) {
        if (array_intersect(['Indent', 'Outdent'], $group['items'] ?? [])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'indentClasses' => self::INDENT_CLASSES,
    ];
  }

}
