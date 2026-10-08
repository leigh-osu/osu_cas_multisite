<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Bulk action: add nodes to another group, keeping them where they are.
 */
#[Action(
  id: 'osu_cas_add_to_group',
  label: new TranslatableMarkup('Add to another group'),
  type: 'node'
)]
class CasAddToGroup extends CasGroupPlacementActionBase {

  /**
   * {@inheritdoc}
   */
  protected function removesFromSource(): bool {
    return FALSE;
  }

}
