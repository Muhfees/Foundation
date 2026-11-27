<?php

namespace Drupal\nusry_core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures demo noindex and baseline security headers on every main response.
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
   * Adds X-Robots-Tag and hardened response headers if missing.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $headers = $event->getResponse()->headers;
    if (!$headers->has('X-Robots-Tag')) {
      $headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
    if (!$headers->has('X-Content-Type-Options')) {
      $headers->set('X-Content-Type-Options', 'nosniff');
    }
    if (!$headers->has('X-Frame-Options')) {
      $headers->set('X-Frame-Options', 'SAMEORIGIN');
    }
    if (!$headers->has('Referrer-Policy')) {
      $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
    if (!$headers->has('Permissions-Policy')) {
      $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
    }
    if (!$headers->has('Content-Security-Policy-Report-Only')) {
      $headers->set(
        'Content-Security-Policy-Report-Only',
        "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'",
      );
    }
  }

}
