<?php

namespace Drupal\osu_cas_multisite_groups;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationship;
use Drupal\node\NodeInterface;

/**
 * Resolves the group a request is "in" — D7's og_context, in short.
 *
 * A group route (the group itself, its members page, ...) names the group
 * outright; a node route resolves through the node's first group
 * relationship; anything else has no group. Views (cas_current_group) and
 * the group-aware blocks share this so every "current group" answer agrees.
 */
class CurrentGroup {

  protected RouteMatchInterface $routeMatch;
  protected DomainNegotiatorInterface $domainNegotiator;
  protected StorageInterface $configStorage;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected Connection $database;

  /**
   * Memoised domain id -> default group, since one request asks repeatedly.
   *
   * @var array<string, \Drupal\group\Entity\GroupInterface|null>
   */
  protected array $domainDefaults = [];

  /**
   * Memoised group id -> whether its menu has any links.
   *
   * @var array<int, bool>
   */
  protected array $menuLinkCounts = [];

  public function __construct(
    RouteMatchInterface $route_match,
    DomainNegotiatorInterface $domain_negotiator,
    StorageInterface $config_storage,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
  ) {
    $this->routeMatch = $route_match;
    $this->domainNegotiator = $domain_negotiator;
    $this->configStorage = $config_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * The current group, or NULL when the request has none.
   */
  public function getGroup(): ?GroupInterface {
    $group = $this->routeMatch->getParameter('group');
    if ($group instanceof GroupInterface) {
      return $group;
    }
    if (is_numeric($group)) {
      return \Drupal::entityTypeManager()->getStorage('group')->load($group);
    }
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      foreach (GroupRelationship::loadByEntity($node) as $relationship) {
        return $relationship->getGroup();
      }
    }
    return NULL;
  }

  /**
   * The current group's id, or 0 when the request has none.
   */
  public function getGroupId(): int {
    $group = $this->getGroup();
    return $group ? (int) $group->id() : 0;
  }

  /**
   * The group whose navigation this request should display.
   *
   * getGroup() answers "which group does this content belong to", and returns
   * NULL on everything that belongs to none — a user page, the search results,
   * an ungrouped node. That is the right answer for a views argument, but the
   * wrong one for the header: a page with no group of its own should still
   * carry the navigation of the site it is being viewed on, not fall back to
   * the near-empty site-wide main menu.
   *
   * So anything without a group of its own gets the current domain's default
   * group. That is derived from the domain's own front page rather than from a
   * dedicated field: every one of the 35 domains sets page.front in its
   * domain.<id> config collection, and every one of those front pages is group
   * content, so the group owning it is by definition the group that owns the
   * domain. Deriving it beats storing it, because it cannot drift out of sync
   * with what the domain actually shows.
   *
   * Note this deliberately does not use Group's own fallback. When a route has
   * no group, GroupRouteContextTrait::getBestCandidate() scans the route's
   * entities and returns the first group any of them belongs to — on a user
   * page that is the account's first membership, so a member of four groups
   * gets an arbitrary one, often on a different domain entirely.
   */
  public function getGroupForDisplay(): ?GroupInterface {
    $group = $this->getGroup();
    if ($group && $this->hasMenuLinks($group)) {
      return $group;
    }
    // Either the page belongs to no group, or it belongs to one whose menu has
    // no links — 18 of the 195 groups are in that state, covering 270 published
    // nodes. Showing them an empty header is worse than showing them the
    // navigation of the site they are on, so both cases take the domain
    // default. If there is no domain default either, the page's own group is
    // still the most truthful answer available.
    return $this->getDomainDefaultGroup() ?? $group;
  }

  /**
   * Whether a group's menu actually has anything in it.
   *
   * group_content_menu gives a group a menu entity as soon as it is created, so
   * "has a menu" and "has navigation" are not the same question.
   */
  protected function hasMenuLinks(GroupInterface $group): bool {
    $id = (int) $group->id();
    if (isset($this->menuLinkCounts[$id])) {
      return $this->menuLinkCounts[$id];
    }
    $this->menuLinkCounts[$id] = FALSE;
    $storage = $this->entityTypeManager->getStorage('group_content');
    foreach ($storage->loadByGroup($group) as $relationship) {
      if (!str_starts_with($relationship->getPluginId(), 'group_content_menu:')) {
        continue;
      }
      $count = (int) $this->database->select('menu_tree', 't')
        ->condition('menu_name', 'group_menu_link_content-' . $relationship->getEntity()->id())
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($count > 0) {
        return $this->menuLinkCounts[$id] = TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The group that owns the active domain, via that domain's front page.
   */
  public function getDomainDefaultGroup(): ?GroupInterface {
    $domain = $this->domainNegotiator->getActiveDomain();
    if (!$domain) {
      return NULL;
    }
    $id = $domain->id();
    if (array_key_exists($id, $this->domainDefaults)) {
      return $this->domainDefaults[$id];
    }
    $this->domainDefaults[$id] = NULL;

    $front = $this->configStorage->createCollection('domain.' . $id)->read('system.site')['page']['front'] ?? NULL;
    if (!$front || !preg_match('~/node/(\d+)~', $front, $matches)) {
      return NULL;
    }
    $node = $this->entityTypeManager->getStorage('node')->load($matches[1]);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }
    foreach (GroupRelationship::loadByEntity($node) as $relationship) {
      return $this->domainDefaults[$id] = $relationship->getGroup();
    }
    return NULL;
  }

}
