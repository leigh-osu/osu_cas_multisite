<?php

namespace Drupal\osu_cas_multisite_groups;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Swaps core's toolbar link builder for one that adds the dashboard link.
 *
 * Redefining the service in services.yml would work only as long as this
 * module is weighted after user, which nothing guarantees. Altering the
 * existing definition is order-independent and leaves core's arguments alone.
 */
class OsuCasMultisiteGroupsServiceProvider extends ServiceProviderBase implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('user.toolbar_link_builder')) {
      $container->getDefinition('user.toolbar_link_builder')
        ->setClass(CasToolbarLinkBuilder::class);
    }
  }

}
