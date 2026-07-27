<?php

namespace Drupal\osu_cas_multisite\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirects a logged-in user to the profile node they own.
 *
 * Profiles were migrated from D7 user accounts with the node's author set to
 * the matching user (upgrade_d7_user_to_profile), so ownership is the link
 * between an account and "their" profile. Users whose profile fell back to
 * uid 1 (no migrated account) have no owned profile and see no link.
 */
class MyProfileController extends ControllerBase {

  /**
   * Redirects to the current user's own profile node.
   */
  public function redirectToProfile() {
    $nid = $this->findProfileNid($this->currentUser());
    if ($nid === NULL) {
      throw new NotFoundHttpException();
    }
    return $this->redirect('entity.node.canonical', ['node' => $nid]);
  }

  /**
   * Access callback: only users who own a profile node get the link.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->isAuthenticated() && $this->findProfileNid($account) !== NULL)
      ->addCacheContexts(['user'])
      ->addCacheTags(['node_list:osu_profile']);
  }

  /**
   * Returns the nid of the profile node owned by the account, if any.
   *
   * Prefers a published profile if the user somehow owns more than one.
   */
  private function findProfileNid(AccountInterface $account): ?int {
    if ($account->isAnonymous()) {
      return NULL;
    }
    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'osu_profile')
      ->condition('uid', $account->id())
      ->sort('status', 'DESC')
      ->sort('nid')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    $nid = reset($nids);
    return $nid === FALSE ? NULL : (int) $nid;
  }

}
