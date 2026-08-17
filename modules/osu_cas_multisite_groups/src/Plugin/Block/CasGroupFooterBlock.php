<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\osu_cas_multisite_groups\CurrentGroup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Group footer: the current group's contact card above the site footer.
 *
 * The D10 fold of D7's group_information footer view, which 20 contexts
 * placed by path into the footer region: embedded map, address, e-mail,
 * phone, hours, social links, the "additional information" link list and
 * an optional fourth column. Placed once in the theme's pre-footer region;
 * renders nothing on pages with no group, on excluded groups (the main
 * College group, whose card is the site footer itself), or when the group
 * has no contact data.
 *
 * @Block(
 *   id = "cas_group_footer",
 *   admin_label = @Translation("Group footer"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasGroupFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected CurrentGroup $currentGroup;
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Social fields, in D7's footer order, with their Font Awesome icons.
   */
  const SOCIAL = [
    'field_group_facebook' => ['Facebook', 'fa-brands fa-facebook-f'],
    'field_group_linkedin' => ['LinkedIn', 'fa-brands fa-linkedin-in'],
    'field_group_twitter' => ['X (Twitter)', 'fa-brands fa-x-twitter'],
    'field_group_youtube' => ['YouTube', 'fa-brands fa-youtube'],
    'field_group_instagram' => ['Instagram', 'fa-brands fa-instagram'],
    'field_group_newsletter_link' => ['Newsletter', 'fa-solid fa-envelope-open-text'],
  ];

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->currentGroup = $container->get('osu_cas_multisite_groups.current_group');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return [
      // The main College group: its contact card is the site footer.
      'exclude_groups' => [228631],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['exclude_groups'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'group',
      '#tags' => TRUE,
      '#title' => $this->t('Groups without a footer'),
      '#description' => $this->t('Pages in these groups show only the site footer.'),
      '#default_value' => $this->configuration['exclude_groups']
        ? $this->entityTypeManager->getStorage('group')->loadMultiple($this->configuration['exclude_groups'])
        : NULL,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $ids = $form_state->getValue('exclude_groups') ?: [];
    $this->configuration['exclude_groups'] = array_values(array_filter(array_map(
      fn($item) => is_array($item) ? (int) ($item['target_id'] ?? 0) : (int) $item, $ids)));
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    $cache = [
      'contexts' => ['route'],
      'tags' => ['group_relationship_list'],
    ];
    $group = $this->currentGroup->getGroup();
    if (!$group || in_array((int) $group->id(), $this->configuration['exclude_groups'] ?? [], TRUE)) {
      return ['#cache' => $cache];
    }
    $cache['tags'] = Cache::mergeTags($cache['tags'], $group->getCacheTags());

    $text = fn(string $field) => $this->text($group, $field);
    $plain = fn(string $field) => $group->hasField($field) ? trim((string) $group->get($field)->value) : '';

    $address = array_filter([
      'name' => $plain('field_group_location_name'),
      'address' => $plain('field_group_location_address'),
      'address2' => $plain('field_group_location_address2'),
      'city' => $plain('field_group_location_city'),
      'state' => $plain('field_group_location_state'),
      'zip' => $plain('field_group_location_zip'),
    ]);
    $email = $plain('field_group_email');
    $phone = $plain('field_group_phone');

    $social = [];
    foreach (self::SOCIAL as $field => [$label, $icon]) {
      $url = $this->link($group, $field);
      if ($url) {
        $social[] = ['label' => $label, 'icon' => $icon, 'url' => $url];
      }
    }
    if ($group->hasField('field_group_info_sheet') && ($media = $group->get('field_group_info_sheet')->entity)) {
      $file = $media->getSource()->getSourceFieldValue($media);
      $file = is_numeric($file) ? $this->entityTypeManager->getStorage('file')->load($file) : NULL;
      if ($file) {
        $social[] = [
          'label' => (string) $this->t('Information sheet'),
          'icon' => 'fa-solid fa-circle-info',
          'url' => $file->createFileUrl(),
        ];
        $cache['tags'] = Cache::mergeTags($cache['tags'], $media->getCacheTags());
      }
    }

    $columns = [
      'map' => $text('field_group_map'),
      'contact' => [
        'address' => $address,
        'email' => $email,
        'phone' => $phone,
        'hours' => $text('field_group_hours'),
        'social' => $social,
      ],
      'info' => $text('field_group_add_info'),
      'col4' => $text('field_group_footer_col4'),
    ];
    $has_contact = $address || $email || $phone || $social || $columns['contact']['hours'];
    if (!$has_contact) {
      $columns['contact'] = [];
    }
    $columns = array_filter($columns);
    if (!$columns) {
      return ['#cache' => $cache];
    }

    return [
      '#theme' => 'cas_group_footer',
      '#group' => $group,
      '#columns' => $columns,
      '#column_count' => count($columns),
      '#attributes' => ['class' => ['cas-group-footer']],
      '#cache' => $cache,
    ];
  }

  /**
   * A formatted-text field as a processed_text render array, or NULL.
   */
  protected function text(GroupInterface $group, string $field): ?array {
    if (!$group->hasField($field) || $group->get($field)->isEmpty()) {
      return NULL;
    }
    $item = $group->get($field)->first();
    if (trim(strip_tags((string) $item->value, '<iframe><img>')) === '') {
      return NULL;
    }
    return [
      '#type' => 'processed_text',
      '#text' => $item->value,
      '#format' => $item->format ?: 'full_html',
    ];
  }

  /**
   * A link or plain URL field's href, or NULL.
   */
  protected function link(GroupInterface $group, string $field): ?string {
    if (!$group->hasField($field) || $group->get($field)->isEmpty()) {
      return NULL;
    }
    $item = $group->get($field)->first();
    $values = $item->getValue();
    if (isset($values['uri'])) {
      return Url::fromUri($values['uri'])->toString();
    }
    $value = trim((string) ($values['value'] ?? ''));
    if ($value === '') {
      return NULL;
    }
    return preg_match('~^https?://~i', $value) ? $value : 'https://' . ltrim($value, '/');
  }

}
