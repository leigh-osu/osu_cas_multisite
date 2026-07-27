<?php

namespace Drupal\osu_cas_multisite\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembershipLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the groups the current user is a member of.
 */
class MyGroupsController extends ControllerBase {

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected GroupMembershipLoaderInterface $membershipLoader;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->membershipLoader = $container->get('group.membership_loader');
    return $instance;
  }

  /**
   * Renders the list of groups the current user belongs to.
   */
  public function build(): array {
    $groups = [];
    foreach ($this->membershipLoader->loadByUser($this->currentUser()) as $membership) {
      $group = $membership->getGroup();
      $groups[$group->id()] = $group;
    }
    uasort($groups, fn($a, $b) => strcasecmp($a->label(), $b->label()));

    $items = [];
    $cache_tags = ['group_content_list'];
    foreach ($groups as $group) {
      $items[] = $group->toLink()->toRenderable();
      $cache_tags = array_merge($cache_tags, $group->getCacheTags());
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('My Groups'),
      '#items' => $items,
      '#empty' => $this->t('You are not a member of any groups.'),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => $cache_tags,
      ],
    ];
  }

  /**
   * Access callback: only users belonging to at least one group get the link.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $has_membership = $account->isAuthenticated()
      && !empty($this->membershipLoader->loadByUser($account));
    return AccessResult::allowedIf($has_membership)
      ->addCacheContexts(['user'])
      ->addCacheTags(['group_content_list']);
  }

}
