<?php

namespace Drupal\osu_cas_multisite\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Profile" tab on a user page, linking to that account's profile node.
 *
 * Resolved through field_profile_user, which records the account a profile
 * describes. Node authorship is not used: uid answers "who may edit this",
 * and the two only coincide because the migration set the author to the
 * subject. Authorship also falls back to uid 1 wherever no account migrated,
 * which is 1,381 of the 2,217 profiles -- keying the tab on it would give
 * user 1 a tab to an arbitrary stranger's profile.
 *
 * The user comes from the route rather than the session, so an administrator
 * looking at someone else's account gets that person's profile. The tab is
 * shown only when there is a profile to reach.
 */
class UserProfileTabController extends ControllerBase {

  /**
   * Redirects to the profile node owned by the user in the route.
   */
  public function redirectToProfile(UserInterface $user) {
    $nid = $this->findProfileNid($user->id());
    if ($nid === NULL) {
      throw new NotFoundHttpException();
    }
    return $this->redirect('entity.node.canonical', ['node' => $nid]);
  }

  /**
   * Access callback: show the tab only when there is a profile to reach.
   *
   * Cached per route user and invalidated when profiles change, so the tab
   * appears as soon as a profile is created for that account.
   */
  public function access(AccountInterface $account, UserInterface $user): AccessResultInterface {
    return AccessResult::allowedIf($this->findProfileNid($user->id()) !== NULL)
      ->addCacheContexts(['route'])
      ->addCacheTags(['node_list:osu_profile']);
  }

  /**
   * Returns the nid of the profile describing the account, if any.
   *
   * Prefers a published profile if an account somehow has more than one.
   */
  private function findProfileNid($uid): ?int {
    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'osu_profile')
      ->condition('field_profile_user', $uid)
      ->sort('status', 'DESC')
      ->sort('nid')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    $nid = reset($nids);
    return $nid === FALSE ? NULL : (int) $nid;
  }

}
