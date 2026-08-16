<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Group people listing: the profiles_group_membership view with options.
 *
 * D7's profiles_membership_larch grew 64 near-identical displays (Faculty
 * list, Grad Students, Emeritus, … with/without specialty, grid, …). This is
 * the streamlined replacement: one block whose settings pick the membership
 * types, the style, and optionally a group other than the current page's.
 *
 * @Block(
 *   id = "cas_group_profiles",
 *   admin_label = @Translation("Group people listing"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasGroupProfilesBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
      'display' => 'list',
      'membership_types' => [],
      'group_override' => NULL,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'membership_types']);
    $options = [];
    foreach ($terms as $term) {
      $options[$term->id()] = $term->label();
    }
    asort($options);
    $form['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#options' => [
        'list' => $this->t('List (photo, name, title, contact)'),
        'grid' => $this->t('Grid (photo and name)'),
      ],
      '#default_value' => $this->configuration['display'],
    ];
    $form['membership_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Membership types'),
      '#description' => $this->t('Show only people holding these types in the group. Leave all unchecked to list everyone.'),
      '#options' => $options,
      '#default_value' => $this->configuration['membership_types'],
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
    $this->configuration['membership_types'] = array_values(array_filter($form_state->getValue('membership_types')));
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $view = Views::getView('profiles_group_membership');
    if (!$view || !$view->access($this->configuration['display'])) {
      return [];
    }
    // No group override: NULL lets the argument default (group of the
    // current page) resolve it, matching D7's og_context behavior.
    $gid = $this->configuration['group_override'] ?: NULL;
    $tids = implode('+', $this->configuration['membership_types']);
    $args = $tids === '' ? [$gid] : [$gid, $tids];
    $build = $view->buildRenderable($this->configuration['display'], $args);
    $build['#cache']['contexts'][] = 'route';
    return $build;
  }

}
