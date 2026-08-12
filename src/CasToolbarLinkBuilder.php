<?php

namespace Drupal\osu_cas_multisite;

use Drupal\Core\Url;
use Drupal\user\ToolbarLinkBuilder;

/**
 * CAS toolbar user tray: site account links, not the user object.
 *
 * Core's tray offers "View profile" / "Edit profile", which are the USER
 * ENTITY's pages — accounts here are CAS-managed and not meant to be
 * edited. Those links are replaced with the site's own account
 * destinations (OSU profile node, groups, authored content), each shown
 * only when its route grants access. Log out is kept, and Unmasquerade
 * appears while masquerading. Swapped in for user.toolbar_link_builder by
 * OsuCasMultisiteServiceProvider. My Groups lives in the
 * osu_cas_multisite_groups submodule; with it disabled the route lookup
 * fails access and the link simply drops out.
 */
class CasToolbarLinkBuilder extends ToolbarLinkBuilder {

  /**
   * {@inheritdoc}
   */
  public function renderToolbarLinks() {
    $build = parent::renderToolbarLinks();
    $links = $build['#links'];
    unset($links['account'], $links['account_edit']);

    $candidates = [
      'my_profile' => ['title' => $this->t('My OSU Profile'), 'route' => 'osu_cas_multisite.my_profile'],
      'my_groups' => ['title' => $this->t('My Groups'), 'route' => 'osu_cas_multisite_groups.my_groups'],
      'my_content' => ['title' => $this->t('My Content'), 'route' => 'view.my_content.page_1'],
    ];
    $new = [];
    foreach ($candidates as $key => $info) {
      $url = Url::fromRoute($info['route']);
      if ($url->access()) {
        $new[$key] = ['title' => $info['title'], 'url' => $url];
      }
    }
    if (\Drupal::hasService('masquerade') && \Drupal::service('masquerade')->isMasquerading()) {
      $new['unmasquerade'] = [
        'title' => $this->t('Unmasquerade'),
        'url' => Url::fromRoute('masquerade.unmasquerade'),
      ];
    }

    $build['#links'] = $new + $links;
    // Route access varies per user (already a context) and the masquerade
    // link per session.
    $build['#cache']['contexts'][] = 'session.is_masquerading';
    return $build;
  }

}
