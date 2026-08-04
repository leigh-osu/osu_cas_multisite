<?php

namespace Drupal\osu_cas_multisite;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Swaps the toolbar user-tray link builder for the CAS variant.
 *
 * @see \Drupal\osu_cas_multisite\CasToolbarLinkBuilder
 */
class OsuCasMultisiteServiceProvider extends ServiceProviderBase {

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
