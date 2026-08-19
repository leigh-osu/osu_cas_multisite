<?php

namespace Drupal\osu_cas_multisite_groups\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * The signed-in user's dashboard.
 *
 * One page per domain at /dashboard, always about the current user: who they
 * are, their profile, the groups they belong to and their role in each, their
 * sitewide roles, and their content. It gathers what used to be scattered
 * across the account menu (My Groups, My Content), which is why those links
 * are gone and this one replaced them.
 *
 * Everything here is per-user and uncacheable across accounts, so the render
 * array carries the 'user' cache context throughout.
 */
class DashboardController extends ControllerBase {

  /**
   * Builds the dashboard.
   */
  public function build(): array {
    $account = $this->currentUser();
    $user = $this->entityTypeManager()->getStorage('user')->load($account->id());

    // Sitewide roles. getRoles(TRUE) drops the locked roles: every signed-in
    // user has 'authenticated', so listing it tells nobody anything.
    $site_roles = [];
    foreach ($this->entityTypeManager()->getStorage('user_role')
      ->loadMultiple($account->getRoles(TRUE)) as $role) {
      $site_roles[] = $role->label();
    }
    natcasesort($site_roles);

    // Groups and content are both views: each brings its own exposed search,
    // 200-row AJAX pager and sortable columns, and each scopes itself to the
    // current user through a contextual filter. The tabs are panes around
    // them, nothing more.
    return [
      '#theme' => 'cas_dashboard',
      '#display_name' => $user ? $user->getDisplayName() : $account->getAccountName(),
      '#account_link' => Url::fromRoute('entity.user.canonical', ['user' => $account->id()]),
      '#profile_link' => $this->profileUrl(),
      '#site_roles' => array_values($site_roles),
      '#groups_view' => views_embed_view('my_groups', 'default'),
      '#content_view' => views_embed_view('my_content', 'default'),
      '#attached' => ['library' => ['osu_cas_multisite_groups/dashboard']],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['group_content_list'],
      ],
    ];
  }

  /**
   * Returns a URL to the current user's own profile node, if they own one.
   *
   * Profiles were migrated from D7 accounts with the node's author set to the
   * matching user, so ownership is the link between an account and "their"
   * profile — the same rule MyProfileController::findProfileNid() applies, and
   * the two must stay in step. Users whose profile fell back to uid 1 own none
   * and get no link.
   */
  private function profileUrl(): ?Url {
    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'osu_profile')
      ->condition('uid', $this->currentUser()->id())
      ->sort('status', 'DESC')
      ->sort('nid')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();
    $nid = reset($nids);
    return $nid === FALSE ? NULL : Url::fromRoute('entity.node.canonical', ['node' => $nid]);
  }

}
