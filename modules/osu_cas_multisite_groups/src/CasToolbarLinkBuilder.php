<?php

namespace Drupal\osu_cas_multisite_groups;

use Drupal\Core\Url;
use Drupal\user\ToolbarLinkBuilder;

/**
 * Rebuilds the admin toolbar's user menu around the dashboard and profile.
 *
 * The toolbar's user tray is not the 'account' menu — core builds it in
 * ToolbarLinkBuilder as a hardcoded list, so a menu link plugin cannot reach
 * it. Extending the builder is the only way in.
 *
 * Core's "View profile" and "Edit profile" both point at the /user account
 * record, which on this site is an implementation detail: the page people mean
 * by "my profile" is their osu_profile node. Those two are dropped and the
 * profile link takes their place, leaving Dashboard, profile and Log out.
 *
 * @see \Drupal\osu_cas_multisite_groups\OsuCasMultisiteGroupsServiceProvider
 * @see user_toolbar()
 */
class CasToolbarLinkBuilder extends ToolbarLinkBuilder {

  /**
   * {@inheritdoc}
   */
  public function renderToolbarLinks() {
    $build = parent::renderToolbarLinks();

    // Core's account links point at /user and /user/N/edit.
    unset($build['#links']['account'], $build['#links']['account_edit']);

    $links = [
      'cas_dashboard' => [
        'title' => $this->t('My Dashboard'),
        'url' => Url::fromRoute('osu_cas_multisite_groups.dashboard'),
        'attributes' => [
          'title' => $this->t('Your profile, groups, roles and content'),
        ],
      ],
    ];

    // Only offered to users who own a profile node: the route's access check
    // is what decides that, and without it the link 404s for everyone else.
    $profile = Url::fromRoute('osu_cas_multisite.my_profile');
    if ($profile->access($this->account)) {
      $links['cas_profile'] = [
        'title' => $this->t('My OSU Profile'),
        'url' => $profile,
        'attributes' => [
          'title' => $this->t('Go to your own profile page'),
        ],
      ];
    }

    $build['#links'] = $links + $build['#links'];

    // Whether the profile link appears varies by user, and it turns on the
    // moment a profile node is created for them.
    $build['#cache']['contexts'][] = 'user';
    $build['#cache']['tags'][] = 'node_list:osu_profile';

    return $build;
  }

}
