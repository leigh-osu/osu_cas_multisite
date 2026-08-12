<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders a node's primary group without joining group relationships.
 *
 * A SQL join against group_relationship_field_data multiplies rows for
 * content placed in many groups (a profile node can sit in 100+), and
 * views aggregation cannot MIN() a string for display. This computed
 * field queries the node's placements at render time instead and shows
 * the alphabetically first group label. Not click-sortable (no SQL
 * column backs it).
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cas_node_groups")
 */
class CasNodeGroups extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Computed at render time; nothing to add to the query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $this->getEntity($values);
    if (!$entity) {
      return '';
    }
    $gids = \Drupal::database()->select('group_relationship_field_data', 'gr')
      ->fields('gr', ['gid'])
      ->condition('gr.entity_id', $entity->id())
      ->condition('gr.plugin_id', 'group\_node:%', 'LIKE')
      ->execute()
      ->fetchCol();
    if (!$gids) {
      return '';
    }
    $labels = [];
    foreach (\Drupal::entityTypeManager()->getStorage('group')->loadMultiple(array_unique($gids)) as $group) {
      $labels[] = $group->label();
    }
    natcasesort($labels);
    return reset($labels) ?: '';
  }

}
