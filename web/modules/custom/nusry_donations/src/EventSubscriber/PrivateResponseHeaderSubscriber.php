<?php

namespace Drupal\nusry_donations\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enforces private, non-cacheable headers on personalized demo routes.
 */
final class PrivateResponseHeaderSubscriber implements EventSubscriberInterface {

  /**
   * Route names whose responses must never be shared-cached or indexed.
   */
  private const PRIVATE_ROUTES = [
    'nusry_donations.receipt',
    'nusry_portal.dashboard',
    'nusry_portal.receipt',
    'nusry_portal.recurring_edit',
    'nusry_portal.payment_method',
    'nusry_forms.donor_services',
    'nusry_forms.donor_services_export',
    'nusry_forms.newsletter_confirm',
    'nusry_forms.newsletter_unsubscribe',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  /**
   * Adds strict private, no-store headers on private demo routes.
   */
  public function onResponse(ResponseEvent $event): void {
    $routeName = (string) $event->getRequest()->attributes->get('_route', '');
    if (!in_array($routeName, self::PRIVATE_ROUTES, TRUE)) {
      return;
    }

    $response = $event->getResponse();
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
  }

}
