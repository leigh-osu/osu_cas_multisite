<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;

/**
 * Shows a referenced domain's label, linked to that domain's home page.
 *
 * The stock entity_reference_label formatter can only link to the entity's
 * canonical route, which for a Domain is its record on the admin screen — of
 * no use to someone scanning a listing and wanting to visit the site. Domain
 * entities know their own front page (getPath()), so link there instead.
 *
 * @FieldFormatter(
 *   id = "cas_domain_home_link",
 *   label = @Translation("Domain label, linked to the domain home page"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CasDomainHomeLink extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getSetting('target_type') === 'domain';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items->referencedEntities() as $delta => $domain) {
      // getPath() is an absolute URL to the domain's front page, scheme and
      // any path prefix included.
      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $domain->label(),
        '#url' => Url::fromUri($domain->getPath()),
        // A domain's hostname and label both live on the domain record.
        '#cache' => ['tags' => $domain->getCacheTags()],
      ];
    }
    return $elements;
  }

}
