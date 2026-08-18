<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Group\RelationHandler;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface;
use Drupal\group\Plugin\Group\RelationHandler\AccessControlTrait;

/**
 * Lets a person edit their own profile even when it is group content.
 *
 * osu_cas_multisite_node_access() grants update to whoever field_profile_user
 * names, which is enough for the 201 profiles that sit in no group. The other
 * 2,016 are group content, and Group does not merely stay neutral when it
 * declines — AccessControl::entityAccess() converts every non-allow into an
 * explicit forbid, precisely so no other module can hand out access it said
 * would not exist. A hook can never win against that, so the grant has to be
 * made inside Group's own chain.
 *
 * Group's own rule is ownership: it allows "update own" when the account owns
 * the node. That covers a profile someone created for themselves, but not one
 * an editor created on their behalf, which is the case field_profile_user
 * exists to record. This adds the subject of the profile alongside its owner,
 * and only ever turns a denial into an allow — never the reverse.
 *
 * Handler services are keyed by relation type, not by derivative, so this sits
 * in front of every group_node bundle — stories, pages, videos and the rest.
 * They are all handed straight to the parent untouched; only osu_profile with
 * an 'update' denial is ever reconsidered.
 *
 * @see \Drupal\group\Plugin\Group\RelationHandlerDefault\AccessControl::entityAccess()
 * @see osu_cas_multisite_node_access()
 */
class ProfileSubjectAccessControl implements AccessControlInterface {

  use AccessControlTrait;

  /**
   * Constructs a new ProfileSubjectAccessControl.
   *
   * @param \Drupal\group\Plugin\Group\RelationHandler\AccessControlInterface $parent
   *   The parent access control handler.
   */
  public function __construct(AccessControlInterface $parent) {
    $this->parent = $parent;
  }

  /**
   * {@inheritdoc}
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account, $return_as_object = FALSE) {
    $result = $this->parent->entityAccess($entity, $operation, $account, TRUE);

    if ($operation === 'update'
      && !$result->isAllowed()
      && !$account->isAnonymous()
      && $entity->getEntityTypeId() === 'node'
      && $entity->bundle() === 'osu_profile'
      && $entity->hasField('field_profile_user')) {
      foreach ($entity->get('field_profile_user') as $item) {
        if ((int) $item->target_id === (int) $account->id()) {
          // Keep the parent's cache metadata: its forbid still applies to
          // every other account, so the result has to stay varied the same way.
          $result = AccessResult::allowed()
            ->addCacheableDependency($result)
            ->addCacheableDependency($entity)
            ->cachePerUser();
          break;
        }
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
