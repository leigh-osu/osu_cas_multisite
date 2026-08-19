<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\group\GroupMembershipLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
 * The list is the group type's own installed group_node plugins rather
 * than a fixed set, so enabling a content type on the group type is all
 * it takes to offer it here. The three everyday types lead; the rest sit
 * alphabetically behind a collapsed "Other", which keeps a list of
 * eighteen from burying the three that get used daily.
 *
 * Members also get the group's content listing beside the links, as a second
 * tab. Non-members see only the create links: the listing is the group's own
 * workspace, and a passer-by has no business reading it. The plugin id stays
 * cas_group_add_content so existing placements keep working.
 *
 * @Block(
 *   id = "cas_group_add_content",
 *   admin_label = @Translation("CAS: group workspace (create content, group content)"),
 *   context_definitions = {
 *     "group" = @ContextDefinition("entity:group")
 *   }
 * )
 */
class CasGroupAddContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The types offered first, in this order.
   */
  private const PRIMARY = ['page', 'story', 'osu_profile'];

  /**
   * Types the group create-content list never offers.
   *
   * Webforms and live feeds are built once by a site editor and then placed,
   * not authored from a group page; simple tabs are a layout device rather
   * than content. Offering them here only invites half-made nodes.
   */
  private const EXCLUDED = ['webform', 'feed', 'osu_simple_tabs'];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The group membership loader.
   *
   * @var \Drupal\group\GroupMembershipLoaderInterface
   */
  protected GroupMembershipLoaderInterface $membershipLoader;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->membershipLoader = $container->get('group.membership_loader');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $group = $this->getContextValue('group');
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user']);
    $cache->addCacheableDependency($group);
    // New content types, or a type newly enabled on the group type, change
    // this list.
    $cache->addCacheTags(['config:node_type_list', 'config:group_content_type_list']);

    $types = $this->availableTypes($group);

    $primary = [];
    foreach (self::PRIMARY as $bundle) {
      if (isset($types[$bundle])) {
        $primary[$bundle] = $types[$bundle];
        unset($types[$bundle]);
      }
    }
    // The remainder reads alphabetically by label. strcasecmp rather than
    // natcasesort: the latter compares letter-by-letter with spaces skipped,
    // which files "Funding Opportunities" ahead of "Fun Facts".
    uasort($types, 'strcasecmp');

    $items = [];
    foreach ($primary as $bundle => $label) {
      if ($link = $this->createLink($group, $bundle, $label, $cache)) {
        $items[$bundle] = $link;
      }
    }

    $other = [];
    foreach ($types as $bundle => $label) {
      if ($link = $this->createLink($group, $bundle, $label, $cache)) {
        $other[$bundle] = $link;
      }
    }
    if ($other) {
      $items['other'] = [
        '#type' => 'details',
        '#title' => $this->t('Other'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['cas-group-add-content__other']],
      ] + $other;
    }

    $links = [];
    if ($items) {
      $links = [
        '#type' => 'container',
        '#attributes' => ['class' => ['cas-group-add-content']],
      ] + $items;
    }

    // The listing is for this group's own people: its members, plus anyone who
    // can already see every node on the site anyway. Membership alone was too
    // narrow — group roles are scoped, so a site administrator who is not a
    // member of a given group resolves to `outsider` there and saw nothing at
    // all. 'bypass node access' is the honest predicate: hiding a listing of
    // nodes from someone who can read all of those nodes protects nothing.
    // Deliberately not 'administer nodes', which the 412 group content authors
    // hold and which would open every group's listing to all of them.
    $listing = NULL;
    $cache->addCacheTags(['group_content_list']);
    $account = \Drupal::currentUser();
    if ($this->membershipLoader->load($group, $account) || $account->hasPermission('bypass node access')) {
      $listing = views_embed_view('group_content_listing', 'default', $group->id());
    }

    $build = [];
    if ($links || $listing) {
      $build = [
        '#theme' => 'cas_group_workspace',
        '#create_links' => $links ?: NULL,
        '#content_view' => $listing,
        '#attached' => ['library' => ['osu_cas_multisite_groups/dashboard']],
      ];
    }
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Returns bundle => label for every node type this group can hold.
   */
  private function availableTypes($group): array {
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    $types = [];
    foreach ($storage->loadByProperties(['group_type' => $group->bundle()]) as $relation) {
      $plugin_id = $relation->getPluginId();
      if (!str_starts_with($plugin_id, 'group_node:')) {
        continue;
      }
      $bundle = substr($plugin_id, strlen('group_node:'));
      if (in_array($bundle, self::EXCLUDED, TRUE) || !isset($node_types[$bundle])) {
        continue;
      }
      $types[$bundle] = (string) $node_types[$bundle]->label();
    }
    return $types;
  }

  /**
   * Builds one create link, or NULL when the user cannot use it.
   */
  private function createLink($group, string $bundle, string $label, CacheableMetadata $cache): ?array {
    $url = Url::fromRoute('entity.group_content.create_form', [
      'group' => $group->id(),
      'plugin_id' => 'group_node:' . $bundle,
    ]);
    $access = $url->access(NULL, TRUE);
    $cache->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return NULL;
    }
    // Plain stacked links — no list markup.
    return [
      '#type' => 'container',
      'link' => [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $url,
      ],
    ];
  }

}
