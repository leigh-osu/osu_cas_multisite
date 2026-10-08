<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\views\access\GroupPermission;

/**
 * Group permission access that also finds the group in the view's argument.
 *
 * Group's plugin takes the group from the current route only. Views Bulk
 * Operations checks view access again on its own routes — the multi-page
 * selection AJAX callback and the action configure form — which carry no
 * group, so every editor, uid 1 included, was refused there: the selection
 * stayed at "Selected 0 items" and configurable actions could not be applied.
 * VBO restores the view's arguments before checking, so the first argument
 * (the gid on group/%group/... displays) stands in for the route's group.
 */
class CasGroupPermission extends GroupPermission {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if (empty($this->group) && is_numeric($gid = $this->view->args[0] ?? NULL)) {
      $group = \Drupal::entityTypeManager()->getStorage('group')->load($gid);
      if ($group instanceof GroupInterface) {
        return $group->hasPermission($this->options['group_permission'], $account);
      }
    }
    return parent::access($account);
  }

}
