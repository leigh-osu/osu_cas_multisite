<?php

namespace Drupal\osu_live_feeds\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * One block per feed node, keyed by nid — mirroring D7 live_feeds' deltas.
 *
 * Feed nids are preserved from D7, so a layout component written by the
 * migration as osu_live_feed:<D7 nid> resolves to the right derivative once
 * the feed nodes exist, regardless of migration order.
 */
class LiveFeedBlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritDoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'feed')
      ->sort('title')
      ->execute();
    foreach ($storage->loadMultiple($nids) as $node) {
      $this->derivatives[$node->id()] = [
        'admin_label' => t('Live Feed: @title', ['@title' => $node->label()]),
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
