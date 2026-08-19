<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\field;

use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\GroupMembership;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders the roles a membership row carries, as a comma-separated list.
 *
 * Group 2.3.x stores membership roles in group_relationship__group_roles,
 * which ships no views data, so the roles cannot be added as a stock field.
 * This reads them through GroupMembership::getRoles(), which also folds in
 * the synchronized roles a member holds by way of their sitewide account —
 * the same set the group permission checks use, so what the dashboard shows
 * is what the user can actually do.
 *
 * Not click-sortable: the values are assembled per row, not in SQL.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cas_membership_roles")
 */
class CasMembershipRoles extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Read from the row's entity; nothing to add to the query.
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $relationship = $this->getEntity($values);
    if (!$relationship instanceof GroupRelationshipInterface) {
      return '';
    }
    $labels = [];
    foreach ((new GroupMembership($relationship))->getRoles() as $role) {
      $labels[] = $role->label();
    }
    if (!$labels) {
      return '';
    }
    natcasesort($labels);
    return $this->sanitizeValue(implode(', ', $labels));
  }

}
