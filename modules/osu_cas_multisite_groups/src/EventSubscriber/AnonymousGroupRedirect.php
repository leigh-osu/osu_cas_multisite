<?php

namespace Drupal\osu_cas_multisite_groups\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends anonymous visitors from /group/N to that group's home page.
 *
 * A group's canonical page is a members' workspace — create-content links and
 * the group's content listing — so anonymous users have never been able to
 * view it. Answering them with a 403 is unhelpful, though: the page they
 * actually wanted is the unit's public home page, which every group names in
 * field_group_home_page. Turn the refusal into a redirect there.
 *
 * This rides on the access denial rather than pre-empting it, so the access
 * rules stay the single source of truth: change who may view a group and this
 * follows automatically.
 */
class AnonymousGroupRedirect implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Ahead of core's ExceptionLoggingSubscriber (50) and the 403 renderer, so
    // the redirect happens instead of a logged access denial.
    return [KernelEvents::EXCEPTION => [['onException', 100]]];
  }

  /**
   * Redirects an anonymous 403 on a group page to the group's home page.
   */
  public function onException(ExceptionEvent $event): void {
    if (!$event->getThrowable() instanceof AccessDeniedHttpException) {
      return;
    }
    if (!$this->currentUser->isAnonymous()) {
      return;
    }
    $request = $event->getRequest();

    // The group is read from the path, not the route parameters: access is
    // checked during routing, so the exception can be thrown before the
    // parameter is upcast onto the request.
    if (!preg_match('#^/group/(\d+)$#', rtrim($request->getPathInfo(), '/'), $match)) {
      return;
    }
    $group = $this->entityTypeManager->getStorage('group')->load($match[1]);
    if (!$group || !$group->hasField('field_group_home_page') || $group->get('field_group_home_page')->isEmpty()) {
      return;
    }
    $home = $group->get('field_group_home_page')->entity;
    // Redirecting to a node the visitor also cannot see would only move the
    // 403; leave those alone.
    if (!$home || !$home->access('view', $this->currentUser)) {
      return;
    }

    // 302, not 301: which node is a unit's home page is editorial and changes.
    $response = new RedirectResponse($home->toUrl()->toString(), 302);
    $response->setMaxAge(0);
    $event->setResponse($response);
  }

}
