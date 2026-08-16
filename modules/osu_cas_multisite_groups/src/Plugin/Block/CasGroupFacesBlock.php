<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Faces of AgSci cards: random profiles-in-story pages from a group.
 *
 * The D10 rebuild of D7's faces_of_agsci embeds: N random cards (cover
 * photo, title, introduction) from the pages tagged with the picked type
 * terms (Student by default). Randomization and de-duplication happen here
 * rather than in SQL — gnode's access join fans multi-group nodes into
 * duplicate rows, and ORDER BY RAND() defeats DISTINCT.
 *
 * @Block(
 *   id = "cas_group_faces",
 *   admin_label = @Translation("Faces of AgSci cards"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasGroupFacesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * How many candidates to draw from before shuffling.
   */
  const POOL = 24;

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
      'items' => 2,
      'terms' => [5],
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
      '#title' => $this->t('How many cards'),
      '#min' => 1,
      '#max' => 12,
      '#default_value' => $this->configuration['items'],
    ];
    $form['terms'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#title' => $this->t('Type terms'),
      '#description' => $this->t('Pages must carry every listed term (Student by default).'),
      '#default_value' => $this->configuration['terms']
        ? $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($this->configuration['terms'])
        : NULL,
    ];
    $form['all_groups'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draw from every group'),
      '#description' => $this->t('Unchecked, only pages in this page&rsquo;s group (or the group below) are shown.'),
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
    $this->configuration['items'] = (int) $form_state->getValue('items') ?: 2;
    $terms = $form_state->getValue('terms') ?: [];
    $this->configuration['terms'] = array_values(array_filter(array_map(
      fn($item) => is_array($item) ? (int) ($item['target_id'] ?? 0) : (int) $item, $terms)));
    $this->configuration['all_groups'] = (bool) $form_state->getValue('all_groups');
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $view = Views::getView('faces_of_agsci');
    if (!$view || !$view->access('cards')) {
      return [];
    }
    $gid = $this->configuration['all_groups']
      ? 'all'
      : ($this->configuration['group_override'] ?: NULL);
    $args = [$gid];
    if ($this->configuration['terms']) {
      // Comma-joined term ids AND together, as D7's stacked tid filters did.
      $args[] = implode(',', $this->configuration['terms']);
    }
    $view->setDisplay('cards');
    $view->setItemsPerPage(self::POOL);
    $view->setArguments($args);
    $view->preExecute();
    $view->execute();
    // Dedupe by node, then a random draw of the configured size.
    $by_nid = [];
    foreach ($view->result as $row) {
      $by_nid[$row->_entity->id()] = $row;
    }
    $rows = array_values($by_nid);
    shuffle($rows);
    $rows = array_slice($rows, 0, $this->configuration['items']);
    foreach ($rows as $i => $row) {
      $row->index = $i;
    }
    $view->result = $rows;
    $build = [
      'view' => $view->render('cards'),
      '#cache' => [
        'contexts' => ['route'],
        'tags' => ['node_list:page', 'group_relationship_list'],
      ],
    ];
    return $build;
  }

}
