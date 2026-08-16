<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationship;
use Drupal\node\NodeInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default views argument: the group of the current page.
 *
 * D7's og_context argument default, rebuilt for Group: on a group route the
 * group itself, on a node route the (first) group the node is placed in.
 * Lets group-scoped listings embed on any group page without configuration.
 *
 * @ViewsArgumentDefault(
 *   id = "cas_current_group",
 *   title = @Translation("Group of the current page")
 * )
 */
class CasCurrentGroup extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function getArgument() {
    $group = $this->routeMatch->getParameter('group');
    if ($group instanceof GroupInterface) {
      return $group->id();
    }
    if (is_numeric($group)) {
      return (int) $group;
    }
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      foreach (GroupRelationship::loadByEntity($node) as $relationship) {
        return $relationship->getGroupId();
      }
    }
    // No group in context: an impossible id so the listing renders empty
    // rather than unfiltered.
    return 0;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() {
    return ['route'];
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags() {
    return [];
  }

}
