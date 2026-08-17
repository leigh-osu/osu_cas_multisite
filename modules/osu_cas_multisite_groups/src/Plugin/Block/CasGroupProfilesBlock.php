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
      'term' => NULL,
      'grad_accept' => [],
      'all_groups' => FALSE,
      'exposed_filter' => 'none',
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
    $form['term'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#title' => $this->t('Limit to a term'),
      '#description' => $this->t('Only people whose profile carries this term (any vocabulary), e.g. an FW division.'),
      '#default_value' => $this->configuration['term']
        ? $this->entityTypeManager->getStorage('taxonomy_term')->load($this->configuration['term'])
        : NULL,
    ];
    $grad_field = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', 'field_profile_fac_accept_grad');
    $form['grad_accept'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Accepting graduate students for'),
      '#description' => $this->t('Only people who accept graduate students for these programs. Leave unchecked to not filter.'),
      '#options' => $grad_field ? $grad_field->getSetting('allowed_values') : [],
      '#default_value' => $this->configuration['grad_accept'],
    ];
    $form['exposed_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Visitor filter'),
      '#description' => $this->t('Show visitors a filter form above the list.'),
      '#options' => [
        'none' => $this->t('None'),
        'directory_division' => $this->t('Division select'),
        'directory_division_courtesy' => $this->t('Division select (courtesy)'),
        'directory_names' => $this->t('Name search'),
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
    $this->configuration['display'] = $form_state->getValue('display');
    $this->configuration['membership_types'] = array_values(array_filter($form_state->getValue('membership_types')));
    $this->configuration['term'] = $form_state->getValue('term') ?: NULL;
    $this->configuration['grad_accept'] = array_values(array_filter($form_state->getValue('grad_accept')));
    $this->configuration['all_groups'] = (bool) $form_state->getValue('all_groups');
    $this->configuration['exposed_filter'] = $form_state->getValue('exposed_filter') ?: 'none';
    $this->configuration['group_override'] = $form_state->getValue('group_override') ?: NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $display = $this->configuration['display'];
    $exposed = $this->configuration['exposed_filter'] ?? 'none';
    if ($exposed !== 'none' && $display === 'list') {
      $display = $exposed;
    }
    $view = Views::getView('profiles_group_membership');
    if (!$view || !$view->access($display)) {
      return [];
    }
    // No group override: NULL lets the argument default (group of the
    // current page) resolve it, matching D7's og_context behavior; 'all'
    // spans every group (D7 views without an og argument).
    $gid = !empty($this->configuration['all_groups'])
      ? 'all'
      : ($this->configuration['group_override'] ?: NULL);
    $types = implode('+', $this->configuration['membership_types']) ?: 'all';
    $term = $this->configuration['term'] ?: 'all';
    $grad = implode('+', $this->configuration['grad_accept'] ?? []) ?: 'all';
    // Positional arguments; trailing 'all' placeholders hit each argument's
    // exception value and are ignored.
    $args = [$gid, $types, $term, $grad];
    while (count($args) > 1 && end($args) === 'all') {
      array_pop($args);
    }
    $view->setDisplay($display);
    $build = $view->buildRenderable($display, $args);
    $build['#cache']['contexts'][] = 'route';
    return $build;
  }

}
