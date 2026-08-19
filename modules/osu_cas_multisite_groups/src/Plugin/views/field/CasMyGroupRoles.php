<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\field;

use Drupal\group\Entity\GroupInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * The roles the current user holds in the group on this row.
 *
 * The companion to CasMembershipRoles, for listings built on groups rather than
 * on membership rows. Group 2.3.x ships no views data for
 * group_relationship__group_roles, so the displayed value is read through the
 * membership API, which also folds in the synchronized roles a member holds by
 * way of their sitewide account.
 *
 * A row with no membership is expected here rather than exceptional: someone
 * administering every group sees groups they have never joined.
 *
 * Sorting is backed by a correlated subquery returning 1 for a membership and
 * 0 without, so the column orders members before non-members and, within each
 * block, alphabetically by group. It deliberately does not try to sort by the
 * rendered role text: synchronized roles are derived from the account at
 * runtime and exist in no column, so any SQL ordering of them would disagree
 * with what the row displays. Membership is the distinction the column is
 * really being sorted on.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cas_my_group_roles")
 */
class CasMyGroupRoles extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $uid = (int) \Drupal::currentUser()->id();
    $this->field_alias = $this->query->addField(NULL, "(SELECT COUNT(*)
      FROM {group_relationship_field_data} cas_mr
      WHERE cas_mr.gid = groups_field_data.id
        AND cas_mr.plugin_id = 'group_membership'
        AND cas_mr.entity_id = {$uid})", 'cas_is_member');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Descending puts members first, which is the useful direction and the one
   * the view opens on.
   */
  public function clickSort($order) {
    if (empty($this->field_alias)) {
      return;
    }
    $this->query->addOrderBy(NULL, NULL, $order, $this->field_alias);
    // Within members and within non-members, read alphabetically.
    $this->query->addOrderBy('groups_field_data', 'label', 'ASC');
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $group = $this->getEntity($values);
    if (!$group instanceof GroupInterface) {
      return '';
    }
    $membership = \Drupal::service('group.membership_loader')->load($group, \Drupal::currentUser());
    if (!$membership) {
      return $this->sanitizeValue($this->options['empty'] ?: '');
    }
    $labels = [];
    foreach ($membership->getRoles() as $role) {
      $labels[] = $role->label();
    }
    if (!$labels) {
      return $this->sanitizeValue($this->t('Member'));
    }
    natcasesort($labels);
    return $this->sanitizeValue(implode(', ', $labels));
  }

}
