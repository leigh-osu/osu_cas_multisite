<?php

namespace Drupal\osu_cas_multisite_groups\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The group's own CAS settings: what it may publish.
 *
 * One tab rather than one per setting — taxonomies are expected to join content
 * types here, and a group already carries seven tabs.
 *
 * The list of things a unit can create is a property of the unit, so it is
 * edited on the unit rather than in a site-wide map: with 195 groups a central
 * table would be unreadable and would need an architect for every change, while
 * this can be delegated to the people who know what their department publishes.
 *
 * Clearing every box means all types, which is what every group did before this
 * setting existed. That default is deliberate and load-bearing: treat empty as
 * "nothing allowed" instead and all 195 groups stop being able to create
 * anything until somebody fills them in.
 *
 * The choice narrows what the group type installs; it cannot extend it. Only
 * the 20 content types set up as group content can appear here at all.
 *
 * This shapes what is offered, and does not enforce it. Nothing rejects a
 * disallowed type on save, and the plain /node/add route is untouched.
 */
class GroupSettingsForm extends FormBase {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'osu_cas_group_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $group = NULL) {
    $form_state->set('group', $group);

    $options = [];
    foreach ($this->entityTypeManager->getStorage('group_content_type')->loadMultiple() as $gct) {
      if ($gct->getGroupTypeId() !== $group->getGroupType()->id()) {
        continue;
      }
      $plugin = $gct->getPluginId();
      if (!str_starts_with($plugin, 'group_node:')) {
        continue;
      }
      $bundle = substr($plugin, 11);
      $type = $this->entityTypeManager->getStorage('node_type')->load($bundle);
      if ($type) {
        $options[$bundle] = $type->label();
      }
    }
    asort($options);

    $selected = [];
    foreach ($group->get('field_group_content_types') as $item) {
      $selected[] = $item->target_id;
    }

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Choose what may be created in <strong>@group</strong>. Leave everything clear to offer all types.', ['@group' => $group->label()]) . '</p>',
    ];
    // Two columns side by side once there is room, stacked below that. The
    // lists are 20 and 39 checkboxes, so on a wide screen the second is
    // otherwise a long scroll past the first.
    $form['columns'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['row']],
    ];
    $form['columns']['types'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['col-lg-6']],
    ];
    $form['columns']['vocabularies'] = [
      '#type' => 'container',
      // Keeps the 2em gap when the columns stack; none when they are level.
      '#attributes' => ['class' => ['col-lg-6', 'mt-4', 'mt-lg-0']],
    ];

    $form['columns']['types']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Content types offered in this group'),
    ];
    $form['columns']['types']['types'] = [
      '#parents' => ['types'],
      '#type' => 'checkboxes',
      // Indented under their heading, so each list reads as belonging to it.
      '#attributes' => ['class' => ['ms-4']],
      // The legend stays for screen readers, which need the checkbox group
      // labelled; the visible heading above it is the h3.
      '#title' => $this->t('Content types offered in this group'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $selected,
      '#description' => $this->t('Content types shown to group users. It does not hide or remove content that already exists.'),
    ];
    // Only vocabularies some node field actually references: the rest are
    // internal lists nobody tags content with.
    $vocab_options = [];
    $field_manager = \Drupal::service('entity_field.manager');
    foreach (array_keys($this->entityTypeManager->getStorage('node_type')->loadMultiple()) as $node_bundle) {
      foreach ($field_manager->getFieldDefinitions('node', $node_bundle) as $definition) {
        if ($definition->getType() !== 'entity_reference' || $definition->getSetting('target_type') !== 'taxonomy_term') {
          continue;
        }
        foreach (array_keys($definition->getSetting('handler_settings')['target_bundles'] ?? []) as $vid) {
          $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($vid);
          if ($vocabulary) {
            $vocab_options[$vid] = $vocabulary->label();
          }
        }
      }
    }
    asort($vocab_options);

    $chosen_vocabs = [];
    foreach ($group->get('field_group_vocabularies') as $item) {
      $chosen_vocabs[] = $item->target_id;
    }

    $form['columns']['vocabularies']['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Taxonomies offered in this group'),
    ];
    $form['columns']['vocabularies']['vocabularies'] = [
      '#parents' => ['vocabularies'],
      '#type' => 'checkboxes',
      // Indented under their heading, so each list reads as belonging to it.
      '#attributes' => ['class' => ['ms-4']],
      '#title' => $this->t('Taxonomies offered in this group'),
      '#title_display' => 'invisible',
      '#options' => $vocab_options,
      '#default_value' => $chosen_vocabs,
      '#description' => $this->t('Choose the taxonomies to show for this group. Content that has an existing taxonomy value will not be changed.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\group\Entity\GroupInterface $group */
    $group = $form_state->get('group');
    $chosen = array_values(array_filter($form_state->getValue('types')));
    $vocabs = array_values(array_filter($form_state->getValue('vocabularies')));
    $group->set('field_group_content_types', $chosen);
    $group->set('field_group_vocabularies', $vocabs);
    $group->save();

    $this->messenger()->addStatus($chosen
      ? $this->formatPlural(count($chosen), '@group now offers 1 content type.', '@group now offers @count content types.', ['@group' => $group->label()])
      : $this->t('@group offers all content types.', ['@group' => $group->label()]));
  }

}
