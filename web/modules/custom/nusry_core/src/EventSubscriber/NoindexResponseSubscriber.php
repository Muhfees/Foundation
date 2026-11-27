<?php

namespace Drupal\nusry_core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures every demo response carries X-Robots-Tag noindex, nofollow.
 */
final class NoindexResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -100],
    ];
  }

  /**
   * Adds X-Robots-Tag header if missing.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    if (!$response->headers->has('X-Robots-Tag')) {
      $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
  }

}
