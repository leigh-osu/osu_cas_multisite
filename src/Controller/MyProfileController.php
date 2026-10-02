<?php

namespace Drupal\osu_cas_multisite\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirects a logged-in user to their own profile node.
 *
 * field_profile_user names the account a profile belongs to — the same link
 * the Profile tab, the /user/N redirect and edit access use. The node author
 * is not that link: profiles created by an editor on someone's behalf are
 * authored by the editor (or uid 1) and would otherwise show no profile.
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
   * Returns the nid of the profile node that names the account, if any.
   *
   * Prefers a published profile if the user somehow has more than one.
   */
  private function findProfileNid(AccountInterface $account): ?int {
    if ($account->isAnonymous()) {
      return NULL;
    }
    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'osu_profile')
      ->condition('field_profile_user', $account->id())
      ->sort('status', 'DESC')
      ->sort('nid')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    $nid = reset($nids);
    return $nid === FALSE ? NULL : (int) $nid;
  }

}
