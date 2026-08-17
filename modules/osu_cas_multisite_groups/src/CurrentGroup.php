<?php

namespace Drupal\osu_cas_multisite_groups;

use Drupal\Core\Routing\RouteMatchInterface;
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

  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
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

}
