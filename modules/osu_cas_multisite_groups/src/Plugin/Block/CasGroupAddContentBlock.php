<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;

/**
 * Links to create CAS content inside a group.
 *
 * Replaces the stock "Group operations" dropbutton on the group
 * dashboard with plain links to the group-content create wizard
 * (/group/N/content/create/group_node:BUNDLE), which creates the node
 * and its group relationship in one flow — content lands in the group
 * automatically. Each link renders only when the wizard route grants
 * the current user access.
 *
 * @Block(
 *   id = "cas_group_add_content",
 *   admin_label = @Translation("CAS: create content in group"),
 *   context_definitions = {
 *     "group" = @ContextDefinition("entity:group")
 *   }
 * )
 */
class CasGroupAddContentBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $types = [
      'page' => $this->t('Basic page'),
      'story' => $this->t('Story'),
      'osu_profile' => $this->t('Profile'),
      'webform' => $this->t('Webform'),
    ];
    $group = $this->getContextValue('group');
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheableDependency($group);

    $items = [];
    foreach ($types as $bundle => $label) {
      $url = Url::fromRoute('entity.group_content.create_form', [
        'group' => $group->id(),
        'plugin_id' => 'group_node:' . $bundle,
      ]);
      $access = $url->access(NULL, TRUE);
      $cache->addCacheableDependency($access);
      if ($access->isAllowed()) {
        // Plain stacked links — no list markup.
        $items[$bundle] = [
          '#type' => 'container',
          'link' => [
            '#type' => 'link',
            '#title' => $label,
            '#url' => $url,
          ],
        ];
      }
    }

    $build = [];
    if ($items) {
      $build = [
        '#type' => 'container',
        '#attributes' => ['class' => ['cas-group-add-content']],
      ] + $items;
    }
    $cache->applyTo($build);
    return $build;
  }

}
