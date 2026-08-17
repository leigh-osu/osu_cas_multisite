<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fun facts: a group's fun facts, random or all, with or without image.
 *
 * The D10 fold of D7's fun_facts view embeds. Random draws are made here
 * (dedupe by node, shuffle, slice) because SQL RAND() defeats DISTINCT once
 * the access joins fan multi-group / multi-domain nodes into duplicate rows.
 *
 * @Block(
 *   id = "cas_fun_facts",
 *   admin_label = @Translation("Fun facts"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasFunFactsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    return ['variant' => 'illustrated', 'items' => 3, 'group_override' => NULL];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['variant'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'random3' => $this->t('Random facts, text only'),
        'illustrated' => $this->t('Random facts with image ("Did you know...")'),
        'all' => $this->t('All facts with image'),
      ],
      '#default_value' => $this->configuration['variant'],
    ];
    $form['items'] = [
      '#type' => 'number',
      '#title' => $this->t('How many (random styles)'),
      '#min' => 1,
      '#max' => 20,
      '#default_value' => $this->configuration['items'],
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
    $this->configuration['variant'] = $form_state->getValue('variant');
    $this->configuration['items'] = (int) $form_state->getValue('items') ?: 3;
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $display = $this->configuration['variant'] ?: 'illustrated';
    $view = Views::getView('fun_facts');
    if (!$view || !$view->access($display)) {
      return [];
    }
    $args = [$this->configuration['group_override'] ?: NULL];
    $view->setDisplay($display);
    $view->setArguments($args);
    if ($display !== 'all') {
      $view->setItemsPerPage(max(24, $this->configuration['items'] * 4));
    }
    $view->preExecute();
    $view->execute();
    $by_nid = [];
    foreach ($view->result as $row) {
      $by_nid[$row->_entity->id()] = $row;
    }
    $rows = array_values($by_nid);
    if ($display !== 'all') {
      shuffle($rows);
      $rows = array_slice($rows, 0, $this->configuration['items']);
    }
    foreach ($rows as $i => $row) {
      $row->index = $i;
    }
    $view->result = $rows;
    return [
      'view' => $view->render($display),
      '#cache' => ['contexts' => ['route'], 'tags' => ['node_list:fun_facts', 'group_relationship_list']],
    ];
  }

}
