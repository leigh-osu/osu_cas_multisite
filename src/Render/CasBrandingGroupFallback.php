<?php

namespace Drupal\osu_cas_multisite\Render;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Render\Element\RenderCallbackInterface;
use Drupal\node\NodeInterface;

/**
 * Names the domain's own unit in the header when a node belongs to no group.
 *
 * osu_groups_basic_group's branding alter sets the header's group link inside
 * `if ($group_content)` and has no else, so a node in no group renders a header
 * with no unit name at all. That is not an edge case here: publications and
 * profiles are deliberately kept outside the group structure, which leaves
 * 2,000+ publications across the department domains -- 1,159 on agsci, 450 on
 * cropandsoil, 281 on emt -- showing an anonymous header.
 *
 * The breadcrumb on those same pages reads correctly, which is what makes the
 * gap look like a data fault rather than a code one. It reads correctly by
 * accident: GroupMenuBreadcrumbBuilder::applies() declines a groupless node, so
 * easy_breadcrumb builds a path trail from the domain's site name instead. Two
 * mechanisms, and only one of them knows what domain it is on.
 *
 * The fallback is the domain's front page and the group that owns it, which is
 * the same answer a reader would give: on emt.oregonstate.edu the front page is
 * node 244496, held by group 16025, "Department of Environmental & Molecular
 * Toxicology".
 *
 * Ordering against the upstream callback deliberately does not matter. Both
 * write the same key, and upstream only writes when the node has a group, so
 * whichever runs second the result is the same: a real group wins, and this
 * fills in only where there was nothing.
 *
 * @see \Drupal\osu_groups_basic_group\OsuGroupsBasicGroupSystemBrandingBlockAlter
 * @see \Drupal\osu_cas_multisite_groups\Breadcrumb\GroupMenuBreadcrumbBuilder::applies()
 */
class CasBrandingGroupFallback implements RenderCallbackInterface {

  /**
   * Fills in the header group link for content that has no group.
   */
  public static function preRender($build) {
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node instanceof NodeInterface) {
      // Group routes and everything else are upstream's business.
      return $build;
    }

    $meta = CacheableMetadata::createFromRenderArray($build);
    // The answer depends on the domain, and on the front page that domain
    // points at.
    $meta->addCacheContexts(['url.site']);
    $meta->addCacheTags(['config:system.site']);

    // A node with a group is upstream's to label.
    if (\Drupal::service('osu_groups.group_handler')->getGroupContentFromNode($node)) {
      $meta->applyTo($build);
      return $build;
    }

    $group = self::domainGroup();
    if (!$group) {
      $meta->applyTo($build);
      return $build;
    }
    $meta->addCacheableDependency($group);
    $meta->applyTo($build);

    // The label, not field_group_short_name: OsuGroupsHandler::getGroupNameFromNode()
    // uses the label, and a header that reads "EMT" on a publication but
    // "Environmental and Molecular Toxicology" on the page beside it would look
    // like two different sites. (The breadcrumb does prefer the short name --
    // it has a row of crumbs to fit, and the header has one line.)
    $name = (string) $group->label();

    // The "Home" group is the main site itself, so naming it in the header
    // would read "College of Agricultural Sciences / Home". The breadcrumb
    // suppresses this group for the same reason; on agsci the site name alone
    // is the correct heading.
    if (strcasecmp($name, 'Home') === 0) {
      return $build;
    }

    // Same element and classes as the upstream callback, so the header is
    // indistinguishable from one on grouped content.
    $build['content']['group_name_link'] = [
      '#type' => 'link',
      '#title' => $name,
      '#url' => $group->toUrl(),
      '#attributes' => [
        'class' => [
          'site-name__group-link',
          'text-decoration-none',
          'osu-text-osuorange',
          'fw-bolder',
        ],
      ],
    ];
    return $build;
  }

  /**
   * The group that owns the current domain's front page, if any.
   */
  protected static function domainGroup() {
    // system.site is overridden per domain, so this is already the front page
    // of the domain being served.
    $front = (string) \Drupal::config('system.site')->get('page.front');
    if ($front === '') {
      return NULL;
    }
    if (preg_match('#^/node/(\d+)$#', $front, $m)) {
      $nid = (int) $m[1];
    }
    else {
      $alias = \Drupal::service('path_alias.repository')->lookupByAlias($front, 'en');
      $nid = $alias ? (int) str_replace('/node/', '', $alias['path']) : 0;
    }
    if (!$nid) {
      return NULL;
    }
    $gid = \Drupal::database()->query(
      "SELECT gid FROM {group_relationship_field_data}
       WHERE entity_id = :nid AND plugin_id LIKE 'group\\_node:%'
       ORDER BY gid ASC",
      [':nid' => $nid]
    )->fetchField();
    return $gid ? \Drupal::entityTypeManager()->getStorage('group')->load($gid) : NULL;
  }

}
