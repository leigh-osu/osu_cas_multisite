<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Topic resource listing: the content_by_term view with options.
 *
 * The D10 fold of D7's articles_by_subject / articles_coarec context blocks:
 * date + headline + teaser lists of stories, pages and videos filtered by
 * taxonomy terms — an "all of" set and an "any of" set — scoped to the
 * page's group, a chosen group, or every group.
 *
 * @Block(
 *   id = "cas_content_by_term",
 *   admin_label = @Translation("Content by term"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasContentByTermBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
      'items' => 0,
      'style' => 'full',
      'term_heading' => FALSE,
      'bundles' => [],
      'terms_all' => [],
      'terms_any' => [],
      'exposed_filter' => 'none',
      'all_groups' => FALSE,
      'group_override' => NULL,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['items'] = [
      '#type' => 'number',
      '#title' => $this->t('How many items'),
      '#description' => $this->t('0 shows everything, paged.'),
      '#min' => 0,
      '#max' => 500,
      '#default_value' => $this->configuration['items'],
    ];
    $form['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'full' => $this->t('Date, headline and teaser'),
        'titles' => $this->t('Titles only'),
      ],
      '#default_value' => $this->configuration['style'],
      '#states' => [
        'visible' => [':input[name="settings[exposed_filter]"]' => ['value' => 'none']],
      ],
    ];
    $form['term_heading'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Head the list with the term'),
      '#description' => $this->t('A linked heading naming each "all of" term above the list, as D7 grouped its lists.'),
      '#default_value' => $this->configuration['term_heading'],
    ];
    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#options' => ['story' => $this->t('Story'), 'page' => $this->t('Basic page'), 'video' => $this->t('Video')],
      '#description' => $this->t('Leave all unchecked for every type.'),
      '#default_value' => $this->configuration['bundles'],
    ];
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $form['terms_all'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#title' => $this->t('Carrying all of these terms'),
      '#default_value' => $this->configuration['terms_all'] ? $term_storage->loadMultiple($this->configuration['terms_all']) : NULL,
    ];
    $form['terms_any'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#title' => $this->t('Carrying any of these terms'),
      '#default_value' => $this->configuration['terms_any'] ? $term_storage->loadMultiple($this->configuration['terms_any']) : NULL,
    ];
    $form['exposed_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Visitor filter'),
      '#description' => $this->t('Show visitors a topic filter above the list.'),
      '#options' => [
        'none' => $this->t('None'),
        'list_swd' => $this->t('SWD crops'),
        'list_turf' => $this->t('Turf topics'),
        'list_veg' => $this->t('Vegetable news (crops/topics/market/location)'),
        'archive_veg_video' => $this->t('Vegetable video (crop/topic/market/location)'),
        'list_nursery' => $this->t('Nursery topics'),
        'archive_nursery' => $this->t('Nursery publications (topics/category/system)'),
        'archive_coarec' => $this->t('COAREC keywords + year + author'),
        'list_owri' => $this->t('OWRI topics'),
        'list_vegtags' => $this->t('Vegetable topics (single select)'),
      ],
      '#default_value' => $this->configuration['exposed_filter'],
    ];
    $form['all_groups'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draw from every group'),
      '#default_value' => $this->configuration['all_groups'],
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
    $tids = fn($v) => array_values(array_filter(array_map(
      fn($item) => is_array($item) ? (int) ($item['target_id'] ?? 0) : (int) $item, $v ?: [])));
    $this->configuration['items'] = (int) $form_state->getValue('items');
    $this->configuration['style'] = $form_state->getValue('style') === 'titles' ? 'titles' : 'full';
    $this->configuration['term_heading'] = (bool) $form_state->getValue('term_heading');
    $this->configuration['bundles'] = array_values(array_filter($form_state->getValue('bundles')));
    $this->configuration['terms_all'] = $tids($form_state->getValue('terms_all'));
    $this->configuration['terms_any'] = $tids($form_state->getValue('terms_any'));
    $this->configuration['exposed_filter'] = $form_state->getValue('exposed_filter') ?: 'none';
    $this->configuration['all_groups'] = (bool) $form_state->getValue('all_groups');
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $display = ($this->configuration['exposed_filter'] ?? 'none') !== 'none'
      ? $this->configuration['exposed_filter']
      : (($this->configuration['style'] ?? 'full') === 'titles' ? 'titles' : 'list');
    $view = Views::getView('content_by_term');
    if (!$view || !$view->access($display)) {
      return [];
    }
    $gid = !empty($this->configuration['all_groups'])
      ? 'all'
      : ($this->configuration['group_override'] ?: NULL);
    $type = implode('+', $this->configuration['bundles'] ?? []) ?: 'all';
    $all = implode(',', $this->configuration['terms_all'] ?? []) ?: 'all';
    $any = implode('+', $this->configuration['terms_any'] ?? []) ?: 'all';
    $args = [$gid, $type, $all, $any];
    while (count($args) > 1 && end($args) === 'all') {
      array_pop($args);
    }
    $view->setDisplay($display);
    // 0 leaves the display's own pager in charge (paged, D7-style); a count
    // caps the list at that many items with no pager.
    if (!empty($this->configuration['items'])) {
      $view->setItemsPerPage($this->configuration['items']);
      $view->display_handler->setOption('pager', ['type' => 'some', 'options' => ['items_per_page' => $this->configuration['items'], 'offset' => 0]]);
    }
    $view->setArguments($args);
    $build = $view->buildRenderable($display, $args);
    $build['#cache']['contexts'][] = 'route';
    if (empty($this->configuration['term_heading']) || empty($this->configuration['terms_all'])) {
      return $build;
    }
    // D7's grouped lists led with the term as a linked h3.
    $out = ['view' => $build, '#cache' => ['contexts' => ['route']]];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($this->configuration['terms_all']);
    foreach ($terms as $term) {
      $out['heading_' . $term->id()] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => ['cas-term-listing__heading']],
        '#weight' => -10,
        'link' => Link::fromTextAndUrl($term->label(), $term->toUrl())->toRenderable(),
        '#cache' => ['tags' => $term->getCacheTags()],
      ];
    }
    return $out;
  }

}
