<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\filter;

use Drupal\osu_cas_multisite_groups\Access\CasGroupOverrideCalculator;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Limits a group listing to the current user's groups — unless they run them all.
 *
 * The dashboard's My Groups tab is built on groups rather than on membership
 * rows, because someone holding 'administer all cas groups' administers every
 * group and needs to see the ones they have not joined. Building on memberships
 * could never show those: a group the user does not belong to has no row.
 *
 * So the restriction lives here instead of in the base table. Ordinary users
 * get an EXISTS against their own memberships; override holders get no
 * condition at all and see the lot.
 *
 * EXISTS rather than a join, for the usual reason: a join against
 * group_relationship_field_data would duplicate a group for a user who somehow
 * holds two membership rows in it.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cas_my_groups")
 */
class CasMyGroups extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('my groups, or all when administering every group');
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $account = \Drupal::currentUser();

    // Administering every group means seeing every group.
    if ($account->hasPermission(CasGroupOverrideCalculator::PERMISSION)) {
      return;
    }

    $this->ensureMyTable();
    $uid = (int) $account->id();
    $this->query->addWhereExpression($this->options['group'], "EXISTS (
      SELECT 1 FROM {group_relationship_field_data} cas_m
      WHERE cas_m.gid = groups_field_data.id
        AND cas_m.plugin_id = 'group_membership'
        AND cas_m.entity_id = {$uid})");
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Which groups a row set contains depends on who is asking, and on whether
    // they hold the override, which is granted by role.
    return array_merge(parent::getCacheContexts(), ['user', 'user.permissions']);
  }

}
