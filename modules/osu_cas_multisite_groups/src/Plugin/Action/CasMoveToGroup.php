<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Bulk action: move nodes out of this group and into another.
 */
#[Action(
  id: 'osu_cas_move_to_group',
  label: new TranslatableMarkup('Move to another group'),
  type: 'node'
)]
class CasMoveToGroup extends CasGroupPlacementActionBase {

  /**
   * {@inheritdoc}
   */
  protected function removesFromSource(): bool {
    return TRUE;
  }

}
