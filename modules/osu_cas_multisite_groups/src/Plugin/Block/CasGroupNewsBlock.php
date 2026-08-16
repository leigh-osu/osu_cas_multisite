<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Group news listing: the news_items_by_group view with options.
 *
 * The D10 fold of D7's news_items / news_items_2019 / news_items_larch
 * (60 displays between them): one block whose settings pick teasers vs the
 * paged archive, how many teasers, spotlight-only, and optionally a group
 * other than the current page's.
 *
 * @Block(
 *   id = "cas_group_news",
 *   admin_label = @Translation("Group news listing"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasGroupNewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return [
      'display' => 'teasers',
      'items' => 5,
      'spotlight' => FALSE,
      'term' => NULL,
      'group_override' => NULL,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'teasers' => $this->t('Teasers (newest first, fixed count)'),
        'list' => $this->t('Full archive (paged)'),
      ],
      '#default_value' => $this->configuration['display'],
    ];
    $form['items'] = [
      '#type' => 'number',
      '#title' => $this->t('How many teasers'),
      '#min' => 1,
      '#max' => 50,
      '#default_value' => $this->configuration['items'],
      '#states' => [
        'visible' => [':input[name="settings[display]"]' => ['value' => 'teasers']],
      ],
    ];
    $form['spotlight'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Spotlight stories only'),
      '#default_value' => $this->configuration['spotlight'],
    ];
    $form['term'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#title' => $this->t('Limit to a term'),
      '#description' => $this->t('Only stories carrying this term (any vocabulary), e.g. a CAS section or tag.'),
      '#default_value' => $this->configuration['term']
        ? $this->entityTypeManager->getStorage('taxonomy_term')->load($this->configuration['term'])
        : NULL,
    ];
    $form['group_override'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'group',
      '#title' => $this->t('Group'),
      '#description' => $this->t('Leave empty to use the group of the page the block is placed on.'),
      '#default_value' => $this->configuration['group_override']
        ? $this->entityTypeManager->getStorage('group')->load($this->configuration['group_override'])
        : NULL,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['display'] = $form_state->getValue('display');
    $this->configuration['items'] = (int) $form_state->getValue('items') ?: 5;
    $this->configuration['spotlight'] = (bool) $form_state->getValue('spotlight');
    $this->configuration['term'] = $form_state->getValue('term') ?: NULL;
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $display = $this->configuration['display'];
    $view = Views::getView('news_items_by_group');
    if (!$view || !$view->access($display)) {
      return [];
    }
    $gid = $this->configuration['group_override'] ?: NULL;
    $args = [$gid, $this->configuration['spotlight'] ? 'story_spotlight' : 'all'];
    if ($this->configuration['term']) {
      $args[] = $this->configuration['term'];
    }
    $view->setDisplay($display);
    if ($display === 'teasers') {
      $view->setItemsPerPage($this->configuration['items']);
    }
    $view->setArguments($args);
    $build = $view->buildRenderable($display, $args);
    $build['#cache']['contexts'][] = 'route';
    return $build;
  }

}
