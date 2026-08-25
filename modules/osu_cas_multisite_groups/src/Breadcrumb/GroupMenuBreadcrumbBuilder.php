<?php

namespace Drupal\osu_cas_multisite_groups\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\node\NodeInterface;

/**
 * Group-aware breadcrumbs for group content nodes.
 *
 * Trail shape: <group name> » <group menu ancestors…> » <current page>.
 * The group crumb uses field_group_short_name when set (falling back to the
 * group label) and links to the group's landing node — the group_node whose
 * title matches the group label, same convention as manzanita's header link.
 * The middle of the trail follows the group content menu hierarchy when the
 * current page has a link in one of the group's menus; pages without a menu
 * link get just <group name> » <current page>.
 *
 * Runs above easy_breadcrumb; non-group routes fall through to it.
 */
class GroupMenuBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    $route_name = (string) $route_match->getRouteName();
    $cacheable_metadata?->addCacheContexts(['route']);
    // Non-canonical node routes (edit, delete, revisions, preview …) get no
    // breadcrumb at all — claiming them with an empty trail keeps
    // easy_breadcrumb from rendering a path-based one on those tabs.
    if (str_starts_with($route_name, 'entity.node.') && $route_name !== 'entity.node.canonical') {
      return TRUE;
    }
    if ($route_name !== 'entity.node.canonical') {
      return FALSE;
    }
    $node = $route_match->getParameter('node');
    return $node instanceof NodeInterface && $this->groupIds($node) !== [];
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addCacheContexts(['route']);

    // Node tabs other than View: empty trail (the theme hides the bar).
    if ($route_match->getRouteName() !== 'entity.node.canonical') {
      return $breadcrumb;
    }

    // Domain front pages: no breadcrumb — the page is the site's top level.
    $breadcrumb->addCacheContexts(['url.path.is_front']);
    if (\Drupal::service('path.matcher')->isFrontPage()) {
      return $breadcrumb;
    }
    // Menu links and group placements are content; a rename, re-parent or
    // re-home must rebuild the trail.
    $breadcrumb->addCacheTags(['menu_link_content_list', 'group_content_list']);

    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $breadcrumb->addCacheableDependency($node);

    $groups = $this->entityTypeManager->getStorage('group')->loadMultiple($this->groupIds($node));

    // Prefer the group whose menu holds a link to this page; the menu also
    // supplies the ancestor trail.
    $group = NULL;
    $menu_link = NULL;
    foreach ($groups as $candidate) {
      foreach (group_content_menu_get_menus_per_group($candidate) as $relationship) {
        $menu_name = GroupContentMenuInterface::MENU_PREFIX . $relationship->getEntity()->id();
        $links = $this->menuLinkManager->loadLinksByRoute('entity.node.canonical', ['node' => $node->id()], $menu_name);
        if ($links) {
          $group = $candidate;
          $menu_link = reset($links);
          break 2;
        }
      }
    }
    $group = $group ?? reset($groups);
    $breadcrumb->addCacheableDependency($group);

    $group_title = $this->groupTitle($group);
    $landing_nid = $this->landingNid($group);

    // The group's landing page is the top of its own trail — no breadcrumb
    // there (an empty trail; the theme hides the empty bar). Claiming the
    // route with an empty result also keeps easy_breadcrumb from rendering
    // a path-based trail in its place.
    if ($landing_nid && (int) $landing_nid === (int) $node->id()) {
      $breadcrumb->setLinks([]);
      return $breadcrumb;
    }

    $links = [];
    // The "Home" group is the main site itself — its name is never shown as
    // a crumb (the trail starts at the menu ancestors instead).
    if (strcasecmp($group_title, 'Home') !== 0) {
      $links[] = $landing_nid
        ? Link::createFromRoute($group_title, 'entity.node.canonical', ['node' => $landing_nid])
        : $group->toLink($group_title);
    }

    // Walk the menu ancestry (root-first), skipping the link to the page
    // itself and anything that duplicates the group crumb.
    if ($menu_link) {
      $parent_ids = array_reverse(array_keys($this->menuLinkManager->getParentIds($menu_link->getPluginId())));
      foreach ($parent_ids as $plugin_id) {
        if ($plugin_id === $menu_link->getPluginId()) {
          continue;
        }
        $parent = $this->menuLinkManager->createInstance($plugin_id);
        $url = $parent->getUrlObject();
        if ($this->urlIsNode($url, $landing_nid) || $this->urlIsNode($url, $node->id())) {
          continue;
        }
        $links[] = new Link($parent->getTitle(), $url);
      }
    }

    // End the trail with the current page's title as a plain-text crumb
    // (matching easy_breadcrumb's include_title_segment / unlinked title).
    $links[] = Link::createFromRoute($node->getTitle(), '<none>');
    $breadcrumb->setLinks($links);
    return $breadcrumb;
  }

  /**
   * Returns the ids of the groups this node belongs to (oldest first).
   */
  protected function groupIds(NodeInterface $node): array {
    return $this->database->query(
      "SELECT gid FROM {group_relationship_field_data} WHERE entity_id = :nid AND plugin_id LIKE 'group_node:%' ORDER BY gid ASC",
      [':nid' => $node->id()]
    )->fetchCol();
  }

  /**
   * The group's display name: field_group_short_name, falling back to label.
   */
  protected function groupTitle(GroupInterface $group): string {
    if ($group->hasField('field_group_short_name') && !$group->get('field_group_short_name')->isEmpty()) {
      return (string) $group->get('field_group_short_name')->value;
    }
    return (string) $group->label();
  }

  /**
   * Finds the group's landing node — same convention as manzanita's header.
   */
  protected function landingNid(GroupInterface $group): ?int {
    $nid = $this->database->queryRange(
      'SELECT n.nid
       FROM {group_relationship_field_data} gr
       JOIN {node_field_data} n ON n.nid = gr.entity_id
       JOIN {groups_field_data} g ON g.id = gr.gid
       WHERE gr.gid = :gid
         AND gr.plugin_id LIKE :plugin
         AND n.title = g.label
         AND n.status = 1
         AND n.default_langcode = 1
       ORDER BY n.nid ASC',
      0, 1,
      [':gid' => $group->id(), ':plugin' => 'group_node:%']
    )->fetchField();
    return $nid ? (int) $nid : NULL;
  }

  /**
   * Whether a menu-link URL points at the given node id.
   */
  protected function urlIsNode(Url $url, ?int $nid): bool {
    return $nid !== NULL
      && $url->isRouted()
      && $url->getRouteName() === 'entity.node.canonical'
      && (int) ($url->getRouteParameters()['node'] ?? 0) === (int) $nid;
  }

}
