<?php

namespace Drupal\osu_cas_multisite\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Group filter rendered as an alphabetical dropdown of group labels.
 *
 * The stock views filter for group_relationship_field_data.gid is plain
 * numeric — unusable as an exposed filter. This InOperator loads every
 * group as its options list (fine at this site's scale), sorted
 * case-insensitively by label and capped at 50 chars for display. A
 * filter applies as an EXISTS subquery against the node's group
 * placements — no join, so filtering can never duplicate rows. Attached
 * to a node_field_data pseudo-field by
 * osu_cas_multisite_views_data_alter().
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cas_group_select")
 */
class CasGroupSelect extends InOperator {

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (empty($this->value)) {
      return;
    }
    $gids = implode(',', array_map('intval', (array) $this->value));
    $this->query->addWhereExpression(
      $this->options['group'],
      "EXISTS (SELECT 1 FROM {group_relationship_field_data} gr
        WHERE gr.entity_id = node_field_data.nid
          AND gr.plugin_id LIKE 'group\\_node:%'
          AND gr.gid IN ($gids))"
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $options = [];
      $groups = \Drupal::entityTypeManager()->getStorage('group')->loadMultiple();
      foreach ($groups as $group) {
        $options[$group->id()] = trim((string) $group->label());
      }
      // Sort on the full labels, then cap for display (50 chars + ellipsis)
      // so the dropdown stays narrow while order follows the real names.
      natcasesort($options);
      foreach ($options as $id => $label) {
        if (mb_strlen($label) > 50) {
          $options[$id] = rtrim(mb_substr($label, 0, 50)) . '…';
        }
      }
      $this->valueOptions = $options;
    }
    return $this->valueOptions;
  }

}
