<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 * Limits a node listing to content that belongs to no group.
 *
 * Content on this site is reached, edited and permissioned through its group,
 * so a node in no group is unreachable in all three senses: it appears in no
 * group listing, and its author can only edit it if some site-wide permission
 * happens to cover it. When 'edit own page content' left the content_authors
 * role on 19 August, 85 published pages became editable by architects and
 * administrators alone.
 *
 * Views cannot express this: an absent relationship is not a value to filter
 * on, and a LEFT JOIN with an IS NULL test both fans out (a node in several
 * groups yields several rows) and interacts badly with other filters. NOT
 * EXISTS says exactly what is meant and stays a single row per node.
 *
 * The plugin_id LIKE is how group_node relationships are told apart from
 * memberships and other relationship types, matching CasGroupNodes.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cas_ungrouped_nodes")
 */
class CasUngroupedNodes extends BooleanOperator {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    $this->valueOptions = [
      1 => $this->t('In no group'),
      0 => $this->t('In at least one group'),
    ];
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $exists = "EXISTS (
      SELECT 1 FROM {group_relationship_field_data} cas_ug
      WHERE cas_ug.entity_id = node_field_data.nid
        AND cas_ug.plugin_id LIKE 'group\\_node:%')";
    // Empty value means the filter is exposed and unset: no condition, so the
    // listing shows everything rather than silently assuming one side.
    if ($this->value === '' || $this->value === NULL || $this->value === []) {
      return;
    }
    $this->query->addWhereExpression($this->options['group'], empty($this->value) ? $exists : "NOT $exists");
  }

}
