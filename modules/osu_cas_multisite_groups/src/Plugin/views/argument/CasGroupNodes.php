<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * Contextual filter: nodes placed in a given group.
 *
 * Listing a group's content by building the view on group relationships looks
 * natural and is a trap: Group filters relationship rows by the
 * "view group_node:BUNDLE relationship" permissions, which only a member's
 * roles carry, so the listing came back empty for everyone else — including
 * administrators who can read every node on the site.
 *
 * Filtering nodes by an EXISTS subquery instead keeps the view on
 * node_field_data, where node access applies and 'bypass node access' means
 * what it says. It is the same shape as the CasGroupSelect filter, and for the
 * same reason: a join against group_relationship_field_data would multiply
 * rows for content placed in several groups.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("cas_group_nodes")
 */
class CasGroupNodes extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    $gid = (int) $this->argument;
    // An unresolved group resolves to 0, which matches nothing — an empty
    // listing rather than every node on the site.
    $this->query->addWhereExpression(0, "EXISTS (
      SELECT 1 FROM {group_relationship_field_data} cas_gr
      WHERE cas_gr.entity_id = node_field_data.nid
        AND cas_gr.plugin_id LIKE 'group\\_node:%'
        AND cas_gr.gid = {$gid})");
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    $group = \Drupal::entityTypeManager()->getStorage('group')->load($this->argument);
    return $group ? $group->label() : $this->t('Group content');
  }

}
