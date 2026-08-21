<?php

namespace Drupal\osu_cas_multisite\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps user account pages for administrators and architects only.
 *
 * The account entity is plumbing: profiles are the public face of a person,
 * and everything an editor should touch lives on the osu_profile node. For
 * anyone who is not an administrator or architect, /user/{uid} and
 * /user/{uid}/edit forward to the person's profile; a user with no profile is
 * simply not there (404).
 *
 * This runs one notch BEFORE routing (priority 33 against the router's 32),
 * which is what makes the behaviour uniform: access to user routes is
 * normally decided inside routing itself (AccessAwareRouter), so a subscriber
 * after routing would only ever see the requests core had already allowed --
 * anonymous visitors would keep getting 403 instead of the profile. Matching
 * on the raw path also means the redirect is cheap: no route rebuild, no
 * entity access shuffle, and the page cache may keep the anonymous redirect.
 *
 * The whole /user/* namespace beyond these two paths -- login, logout, reset,
 * cancel, CAS callbacks -- is deliberately untouched.
 */
class UserPageGuardSubscriber implements EventSubscriberInterface {

  /**
   * Roles whose members keep the native account pages.
   */
  private const PRIVILEGED_ROLES = ['administrator', 'architect'];

  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Before RouterListener (32), after authentication (300).
    return [KernelEvents::REQUEST => ['onRequest', 33]];
  }

  /**
   * Forwards non-privileged visits to user pages onto the profile node.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    if (!preg_match('#^/user/(\d+)(?:/edit)?/?$#', $event->getRequest()->getPathInfo(), $m)) {
      return;
    }

    if ((int) $this->currentUser->id() === 1
      || array_intersect(self::PRIVILEGED_ROLES, $this->currentUser->getRoles())) {
      return;
    }

    // Other sites on this codebase have no osu_profile type; leave them alone.
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    if (!$node_type_storage->load('osu_profile')) {
      return;
    }

    $uid = (int) $m[1];
    $user_storage = $this->entityTypeManager->getStorage('user');
    if (!$user_storage->load($uid)) {
      // No such account: let routing produce its own 404.
      return;
    }

    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'osu_profile')
      ->condition('field_profile_user', $uid)
      ->sort('nid')
      ->range(0, 1)
      ->execute();

    if (!$nids) {
      throw new NotFoundHttpException();
    }

    $url = Url::fromRoute('entity.node.canonical', ['node' => reset($nids)])->toString();
    $event->setResponse(new RedirectResponse($url, 302));
  }

}
