<?php

namespace Drupal\osu_live_feeds\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\osu_live_feeds\FeedFetcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a feed node's external items, one derivative per feed.
 *
 * @Block(
 *   id = "osu_live_feed",
 *   admin_label = @Translation("Live Feed"),
 *   category = @Translation("OSU CAS"),
 *   deriver = "Drupal\osu_live_feeds\Plugin\Derivative\LiveFeedBlockDeriver"
 * )
 */
class LiveFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected FeedFetcher $fetcher;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fetcher = $container->get('osu_live_feeds.fetcher');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $node = $this->entityTypeManager->getStorage('node')->load($this->getDerivativeId());
    if (!$node || $node->bundle() !== 'feed') {
      return [];
    }
    $build = osu_live_feeds_build_items($node);
    $build['#cache']['tags'][] = 'node:' . $node->id();
    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheMaxAge() {
    return FeedFetcher::CACHE_OK;
  }

}
