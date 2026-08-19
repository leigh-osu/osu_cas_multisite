<?php

namespace Drupal\osu_cas_multisite_groups\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flexible_permissions\CalculatedPermissionsItem;
use Drupal\flexible_permissions\PermissionCalculatorBase;
use Drupal\flexible_permissions\RefinableCalculatedPermissions;
use Drupal\group\PermissionScopeInterface;

/**
 * Grants full group permissions to holders of a site-wide override permission.
 *
 * Group deliberately ignores site permissions: GroupPermissionChecker consults
 * only the individual, insider and outsider scopes, so a site administrator who
 * is not a member of a group resolves to `outsider` there and can do nothing
 * beyond viewing. Group 1.x had a 'bypass group access' permission for this;
 * Group 2.x removed it (see group.install, which strips it from every role).
 *
 * The site's existing answer has been blunt: the casadmin account is an
 * explicit member of all 195 groups with the admin group role in each, which
 * works but buries the privilege in 195 membership rows, puts that account in
 * every group's member list, and is invisible in the permissions UI.
 *
 * This calculator restores the capability properly. Accounts holding
 * 'administer all cas groups' are granted an admin permission item in both
 * synchronized scopes, which means every group of every type: insider covers
 * the groups they belong to, outsider the rest. Because it feeds the same
 * calculated-permissions object Group builds for everyone, it applies to every
 * access check that goes through group permissions — entity access, the
 * create-content wizard, relationship access and views query access alike —
 * and participates in Group's permission hash, so it caches correctly.
 *
 * @see \Drupal\group\Access\SynchronizedGroupPermissionCalculator
 * @see \Drupal\group\Access\GroupPermissionChecker::hasPermissionInGroup()
 */
class CasGroupOverrideCalculator extends PermissionCalculatorBase {

  /**
   * The permission that grants the override.
   */
  public const PERMISSION = 'administer all cas groups';

  /**
   * Constructs the calculator.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, $scope) {
    $calculated_permissions = parent::calculatePermissions($account, $scope);
    assert($calculated_permissions instanceof RefinableCalculatedPermissions);

    // Only the synchronized scopes: individual permissions are per-group and
    // would mean enumerating every group, which is exactly the cost this
    // avoids.
    if ($scope !== PermissionScopeInterface::OUTSIDER_ID && $scope !== PermissionScopeInterface::INSIDER_ID) {
      return $calculated_permissions;
    }

    if (!$account->hasPermission(self::PERMISSION)) {
      return $calculated_permissions;
    }

    // A new group type would need an item of its own.
    $calculated_permissions->addCacheTags(['config:group_type_list']);

    foreach (array_keys($this->entityTypeManager->getStorage('group_type')->loadMultiple()) as $group_type_id) {
      // The admin flag is what grants everything: CalculatedPermissionsItem
      // stores no permission list when it is set, and hasPermission() returns
      // TRUE for any permission asked of it.
      $calculated_permissions->addItem(new CalculatedPermissionsItem($scope, $group_type_id, [], TRUE));
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts($scope) {
    if ($scope === PermissionScopeInterface::OUTSIDER_ID || $scope === PermissionScopeInterface::INSIDER_ID) {
      // The override is granted by role, so the result varies the same way
      // Group's own synchronized permissions do.
      return ['user.roles'];
    }
    return [];
  }

}
