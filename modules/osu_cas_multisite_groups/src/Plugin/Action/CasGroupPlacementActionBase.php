<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\node\NodeInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared base for the "Move to another group" / "Add to another group" actions.
 *
 * Group placement is a group_relationship entity, not a node field, so
 * "Modify field values" (views_bulk_edit) cannot change it reliably; these
 * actions write the relationships directly.
 *
 * - Move: the node joins the target group and leaves the source group. The
 *   source is the group whose Nodes tab the action runs from (the view's
 *   first argument); without one, the node leaves every other group.
 * - Add: the node joins the target group and stays where it is.
 *
 * Both halves are checked against Group permissions — "create
 * group_node:BUNDLE relationship" in the target, delete access on each
 * relationship being removed — so a group admin can only move content
 * between groups they manage.
 *
 * Menu links in the old group's menu are left alone (deleting them would also
 * orphan any children) but reported, so the editor can tidy them up.
 */
abstract class CasGroupPlacementActionBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected GroupRelationTypeManagerInterface $relationTypeManager,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('group_relation_type.manager'),
      $container->get('plugin.manager.menu.link'),
      $container->get('current_user'),
    );
  }

  /**
   * Whether the node leaves the source group(s) once it joins the target.
   */
  abstract protected function removesFromSource(): bool;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['target_gid' => NULL];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $source = $this->getSourceGroup();

    $options = [];
    foreach ($this->entityTypeManager->getStorage('group')->loadMultiple() as $group) {
      if ($source && $group->id() === $source->id()) {
        continue;
      }
      if ($this->canAddAnyNode($group)) {
        $options[$group->id()] = $group->label();
      }
    }
    uasort($options, 'strcasecmp');

    $form['target_gid'] = [
      '#type' => 'select',
      '#title' => $this->t('Target group'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a group -'),
      '#default_value' => $this->configuration['target_gid'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->removesFromSource()
        ? ($source
          ? $this->t('The content leaves %group. Only groups you can add content to are listed.', ['%group' => $source->label()])
          : $this->t('The content leaves every other group. Only groups you can add content to are listed.'))
        : $this->t('The content also stays where it is. Only groups you can add content to are listed.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account ??= $this->currentUser;
    $result = AccessResult::neutral();

    if ($object instanceof NodeInterface && ($target = $this->getTargetGroup())) {
      $plugin_id = 'group_node:' . $object->bundle();
      $allowed = $target->getGroupType()->hasPlugin($plugin_id)
        && ($target->getRelationshipsByEntity($object, $plugin_id)
          || $target->hasPermission($this->createPermission($plugin_id), $account));
      if ($allowed && $this->removesFromSource()) {
        foreach ($this->relationshipsToRemove($object) as $relationship) {
          $allowed = $allowed && $relationship->access('delete', $account);
        }
      }
      $result = AccessResult::allowedIf($allowed)->setCacheMaxAge(0);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $target = $this->getTargetGroup();
    $plugin_id = 'group_node:' . $entity->bundle();

    if (!$target->getRelationshipsByEntity($entity, $plugin_id)) {
      $target->addRelationship($entity, $plugin_id);
    }

    if (!$this->removesFromSource()) {
      return $this->t('Added to @group', ['@group' => $target->label()]);
    }

    foreach ($this->relationshipsToRemove($entity) as $relationship) {
      $old_group = $relationship->getGroup();
      $relationship->delete();
      if ($this->groupMenuLinkCount($old_group, $entity)) {
        $this->messenger()->addWarning($this->t('%title is still linked from the %group menu.', [
          '%title' => $entity->label(),
          '%group' => $old_group->label(),
        ]));
      }
    }

    return $this->t('Moved to @group', ['@group' => $target->label()]);
  }

  /**
   * The group the action runs from: the view's group argument, if any.
   */
  protected function getSourceGroup(): ?GroupInterface {
    $args = $this->context['arguments'] ?? $this->view?->args ?? [];
    $gid = $args[0] ?? NULL;
    return is_numeric($gid) ? $this->entityTypeManager->getStorage('group')->load($gid) : NULL;
  }

  /**
   * The group chosen in the configuration form.
   */
  protected function getTargetGroup(): ?GroupInterface {
    $gid = $this->configuration['target_gid'] ?? NULL;
    return $gid ? $this->entityTypeManager->getStorage('group')->load($gid) : NULL;
  }

  /**
   * The node's relationships a move takes away: everything outside the target
   * when there is no source group, otherwise only the source's.
   *
   * @return \Drupal\group\Entity\GroupRelationshipInterface[]
   */
  protected function relationshipsToRemove(NodeInterface $node): array {
    $target = $this->getTargetGroup();
    $source = $this->getSourceGroup();
    $relationships = $this->entityTypeManager->getStorage('group_content')
      ->loadByEntity($node, 'group_node:' . $node->bundle());
    return array_filter($relationships, fn($relationship) =>
      $relationship->getGroupId() != $target->id()
      && (!$source || $relationship->getGroupId() == $source->id()));
  }

  /**
   * Whether the current user can relate a node of at least one bundle here.
   */
  protected function canAddAnyNode(GroupInterface $group): bool {
    foreach ($this->relationTypeManager->getInstalledIds($group->getGroupType()) as $plugin_id) {
      if (str_starts_with($plugin_id, 'group_node:') && $group->hasPermission($this->createPermission($plugin_id), $this->currentUser)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * The Group permission for relating an existing entity via a plugin.
   */
  protected function createPermission(string $plugin_id): string {
    return $this->relationTypeManager->getPermissionProvider($plugin_id)->getPermission('create', 'relationship');
  }

  /**
   * How many links in the group's content menus point at the node.
   */
  protected function groupMenuLinkCount(GroupInterface $group, NodeInterface $node): int {
    $count = 0;
    foreach ($group->getRelationships() as $relationship) {
      if (str_starts_with($relationship->getPluginId(), 'group_content_menu:')) {
        $menu_name = GroupContentMenuInterface::MENU_PREFIX . $relationship->getEntityId();
        $count += count($this->menuLinkManager->loadLinksByRoute('entity.node.canonical', ['node' => $node->id()], $menu_name));
      }
    }
    return $count;
  }

}
