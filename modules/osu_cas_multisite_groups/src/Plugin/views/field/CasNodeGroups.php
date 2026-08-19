<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders a node's primary group without joining group relationships.
 *
 * A SQL join against group_relationship_field_data multiplies rows for
 * content placed in many groups (a profile node can sit in 100+), and
 * views aggregation cannot MIN() a string for display. This field adds a
 * correlated subquery instead: one extra column, no extra rows, holding
 * the alphabetically first group label. That column backs both the
 * rendered value and click-sorting, so the table sorts by exactly what
 * it shows.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cas_node_groups")
 */
class CasNodeGroups extends FieldPluginBase {

  /**
   * {@inheritdoc}
   *
   * The subquery is correlated against node_field_data.nid; the field is
   * only ever attached to that table (see
   * osu_cas_multisite_groups_views_data_alter()).
   */
  public function query() {
    $this->field_alias = $this->query->addField(NULL, "(SELECT MIN(g.label)
      FROM {groups_field_data} g
      INNER JOIN {group_relationship_field_data} gr ON gr.gid = g.id
      WHERE gr.entity_id = node_field_data.nid
        AND gr.plugin_id LIKE 'group\\_node:%')", 'cas_primary_group');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    if (!empty($this->field_alias)) {
      $this->query->addOrderBy(NULL, NULL, $order, $this->field_alias);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Normal path: the subquery column is on the row.
    $label = $this->getValue($values);
    if ($label !== NULL && $label !== '') {
      return $this->sanitizeValue($label);
    }

    // Fallback for callers that render this field outside its own query
    // (preview, or a display that dropped the field from the query).
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
    return $this->sanitizeValue(reset($labels) ?: '');
  }

}
