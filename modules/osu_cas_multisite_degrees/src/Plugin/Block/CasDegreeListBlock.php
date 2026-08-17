<?php

namespace Drupal\osu_cas_multisite_degrees\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Degree fact sheet cards: the degree_fact_sheets view with options.
 *
 * The D10 fold of D7's degree_fact_sheets_list embeds: a card grid of the
 * group's degree fact sheets with the D7 exposed filters (Degree Type,
 * Focus area, Location), in the D7 display variants.
 *
 * @Block(
 *   id = "cas_degree_list",
 *   admin_label = @Translation("Degree fact sheet cards"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasDegreeListBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    return ['variant' => 'all', 'group_override' => NULL];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['variant'] = [
      '#type' => 'select',
      '#title' => $this->t('Programs'),
      '#options' => [
        'all' => $this->t('All programs (filters: type, focus, location)'),
        'undergrad' => $this->t('Undergraduate programs (filters)'),
        'undergrad_es' => $this->t('Programas de licenciatura (Español)'),
        'grad' => $this->t('Graduate programs (filters: type, location)'),
        'eou_bs' => $this->t('EOU: Bachelor of Science'),
        'eou_minors' => $this->t('EOU: Minors'),
      ],
      '#default_value' => $this->configuration['variant'],
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
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $display = $this->configuration['variant'] ?: 'all';
    $view = Views::getView('degree_fact_sheets');
    if (!$view || !$view->access($display)) {
      return [];
    }
    $args = [$this->configuration['group_override'] ?: NULL];
    $view->setDisplay($display);
    $view->setArguments($args);
    $build = $view->buildRenderable($display, $args);
    $build['#cache']['contexts'][] = 'route';
    return $build;
  }

}
