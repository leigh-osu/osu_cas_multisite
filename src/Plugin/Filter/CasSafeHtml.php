<?php

namespace Drupal\osu_cas_multisite\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Removes markup that runs code, and keeps everything else as written.
 *
 * Made for the Profile HTML format, which any signed-in person may use on
 * their own profile. Core's filter_html cannot serve there: its tag allow-list
 * always strips inline style attributes, and hundreds of profiles rely on
 * them for fonts, margins and alignment. So this is a short deny-list
 * instead: elements that execute, embed or frame, event-handler attributes,
 * and script URLs. Nothing else is touched.
 *
 * The list is sized to what profiles hold (paragraphs, lists, images, media
 * embeds, tables, inline styles) and to how a current browser runs code. CSS
 * expressions, srcset and form actions are not handled: the first no longer
 * executes anywhere, and the others cannot reach this content once the
 * elements that carry them are gone.
 *
 * Only output is affected. Stored values stay as written, so turning the
 * filter off restores the original rendering, and a value with nothing to
 * remove is returned byte for byte.
 *
 * Declared as an irreversible transform, not an HTML restrictor: the CKEditor
 * 4 module derives its content rules from restrictors and expects an
 * allow-list, so a deny-only restrictor would make the editor drop every tag.
 */
#[Filter(
  id: "osu_cas_safe_html",
  title: new TranslatableMarkup("Remove scripts, embeds and event handlers"),
  description: new TranslatableMarkup("Removes script, style, iframe, form and plugin elements, event-handler attributes and script URLs. Inline styles and all other markup are kept."),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  weight: -50,
)]
class CasSafeHtml extends FilterBase {

  /**
   * Elements removed together with their contents.
   */
  const REMOVE_ELEMENTS = [
    'script', 'style', 'noscript', 'template',
    'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
    'form', 'input', 'select', 'textarea', 'button',
    'meta', 'link', 'base',
    // SVG animation can rewrite an href into a script URL.
    'animate', 'animatemotion', 'animatetransform', 'set',
  ];

  /**
   * Attributes whose value is a URL the browser will follow.
   */
  const URL_ATTRIBUTES = ['href', 'src', 'xlink:href'];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    if (!str_contains($text, '<')) {
      return new FilterProcessResult($text);
    }
    $dom = Html::load($text);
    if ($this->clean($dom)) {
      $text = Html::serialize($dom);
    }
    return new FilterProcessResult($text);
  }

  /**
   * Cleans a parsed document in place.
   *
   * @return bool
   *   TRUE when anything was removed or rewritten.
   */
  public function clean(\DOMDocument $dom): bool {
    $changed = FALSE;

    foreach (self::REMOVE_ELEMENTS as $tag) {
      foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
        $node->parentNode?->removeChild($node);
        $changed = TRUE;
      }
    }

    foreach (iterator_to_array((new \DOMXPath($dom))->query('//@*')) as $attribute) {
      $name = strtolower($attribute->nodeName);
      $element = $attribute->ownerElement;
      if (str_starts_with($name, 'on')) {
        $element->removeAttributeNode($attribute);
        $changed = TRUE;
      }
      elseif (in_array($name, self::URL_ATTRIBUTES, TRUE)) {
        // Core's protocol allow-list; it also catches padded and entity-encoded
        // spellings such as "java&#9;script:".
        $safe = UrlHelper::stripDangerousProtocols($attribute->nodeValue);
        if ($safe !== $attribute->nodeValue) {
          $element->setAttribute($attribute->nodeName, $safe);
          $changed = TRUE;
        }
      }
    }

    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Scripts, embedded frames, forms and event handlers are removed.');
  }

}
