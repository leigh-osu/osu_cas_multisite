<?php

namespace Drupal\osu_cas_multisite_groups\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\osu_cas_multisite_groups\CurrentGroup;

/**
 * Supplies the group whose navigation the header should show.
 *
 * Group ships its own group context, and the header menu block used it. On a
 * group page or a piece of group content it is right. Everywhere else it is
 * not: GroupRouteContextTrait::getBestCandidate() falls back to scanning the
 * route's entities for anything that belongs to a group, so a user page hands
 * back that account's first membership. A person in four groups therefore got
 * an arbitrary one of them in the header, frequently pointing at a different
 * domain than the one they were reading.
 *
 * This provider answers the question the header actually asks — "which site am
 * I on?" — by falling back to the domain's own default group instead. Group's
 * context is left alone for everything else that may rely on it.
 *
 * Only the blocks that opt in via context_mapping are affected.
 *
 * @see \Drupal\osu_cas_multisite_groups\CurrentGroup::getGroupForDisplay()
 */
class GroupDisplayContext implements ContextProviderInterface {

  use StringTranslationTrait;

  protected CurrentGroup $currentGroup;

  public function __construct(CurrentGroup $current_group) {
    $this->currentGroup = $current_group;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $definition = EntityContextDefinition::fromEntityTypeId('group')
      ->setRequired(FALSE)
      ->setLabel($this->t('Group for display'));

    $cacheability = new CacheableMetadata();
    // Varies by route (which group the content belongs to) and by domain (which
    // default applies when it belongs to none).
    $cacheability->setCacheContexts(['route', 'url.site']);

    $group = $this->currentGroup->getGroupForDisplay();
    if ($group) {
      $cacheability->addCacheableDependency($group);
    }

    $context = new Context($definition, $group);
    $context->addCacheableDependency($cacheability);

    return ['group' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return [
      'group' => EntityContext::fromEntityTypeId('group', $this->t('Group for display')),
    ];
  }

}
