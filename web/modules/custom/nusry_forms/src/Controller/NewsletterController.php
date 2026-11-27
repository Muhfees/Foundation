<?php

namespace Drupal\nusry_forms\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\nusry_forms\Service\NewsletterGatewayInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles double opt-in newsletter page, subscription, confirmation & opt-out.
 */
final class NewsletterController extends ControllerBase {

  /**
   * Constructs a NewsletterController instance.
   */
  public function __construct(
    private readonly NewsletterGatewayInterface $gateway,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_forms.newsletter_gateway'),
    );
  }

  /**
   * Renders a dedicated accessible newsletter signup page.
   */
  public function page(Request $request): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'banner' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['demo-notice-banner'], 'role' => 'note'],
        'pill' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['demo-pill']],
          '#value' => $this->t('Demo mail-catcher — no real emails sent'),
        ],
        'copy' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Sign up to test double opt-in email confirmation and optional independent SMS alerts.'),
        ],
      ],
      'panel' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['preview-panel']],
        'form' => [
          '#type' => 'inline_template',
          '#template' => '
            <form class="nusry-newsletter-form" method="post" action="/newsletter/subscribe" data-newsletter-form>
              <div class="nusry-field">
                <label for="nl-page-email">Email address (demo)</label>
                <input id="nl-page-email" name="email" type="email" required placeholder="supporter@example.test" autocomplete="email" />
              </div>
              <div class="nusry-field nusry-field--checkbox">
                <input id="nl-page-sms" name="sms_opt_in" type="checkbox" value="1" />
                <label for="nl-page-sms">Also opt in to optional demo SMS updates (separate consent)</label>
              </div>
              <div class="nusry-field">
                <label for="nl-page-phone">Mobile number (demo, required only if SMS checked)</label>
                <input id="nl-page-phone" name="phone" type="tel" placeholder="+1-555-0100" autocomplete="tel" />
              </div>
              <div class="visually-hidden" aria-hidden="true">
                <label for="nl-page-website">Leave empty</label>
                <input id="nl-page-website" name="website" type="text" tabindex="-1" autocomplete="off" />
              </div>
              <button class="btn btn--primary" type="submit">Subscribe to demo updates</button>
              <p class="nl-form__msg" role="status" aria-live="polite"></p>
            </form>',
        ],
      ],
    ];
  }

  /**
   * Accepts JSON or progressive HTML POST subscriptions into pending state.
   */
  public function subscribe(Request $request): JsonResponse|array {
    $isJson = str_contains((string) $request->headers->get('Accept', ''), 'application/json')
      || str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')
      || $request->isXmlHttpRequest();

    $data = str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')
      ? (json_decode((string) $request->getContent(), TRUE) ?: [])
      : $request->request->all();

    $email = (string) ($data['email'] ?? '');
    $smsOptIn = !empty($data['sms_opt_in']);
    $phone = (string) ($data['phone'] ?? '');
    $honeypot = trim((string) ($data['website'] ?? '')) !== '';
    $ip = (string) ($request->getClientIp() ?? '127.0.0.1');

    try {
      $result = $this->gateway->subscribe($email, $smsOptIn, $phone, $honeypot, $ip);
    }
    catch (\OverflowException $e) {
      if ($isJson) {
        return new JsonResponse(['status' => 'rate_limited', 'message' => $e->getMessage()], 429);
      }
      return $this->renderStatusPanel('Demo rate limit reached', $e->getMessage(), FALSE);
    }
    catch (\InvalidArgumentException $e) {
      if ($isJson) {
        return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
      }
      return $this->renderStatusPanel('Subscription not completed', $e->getMessage(), FALSE);
    }

    $message = $this->t('Pending confirmation (demo). No real email was sent — open the demo confirmation link to complete opt-in.');
    if ($isJson) {
      return new JsonResponse([
        'status' => 'pending',
        'message' => (string) $message,
        'demo_confirm_url' => $result['demo_confirm_url'],
        'sms_opt_in' => $result['sms_opt_in'],
      ]);
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'panel' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['preview-panel'], 'role' => 'status'],
        'pill' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['demo-pill']],
          '#value' => $this->t('Pending confirmation (demo mail-catcher)'),
        ],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $message,
        ],
        'confirm_link' => [
          '#type' => 'link',
          '#title' => $this->t('Confirm demo subscription now'),
          '#url' => Url::fromUserInput($result['demo_confirm_url']),
          '#attributes' => ['class' => ['btn', 'btn--primary']],
        ],
      ],
    ];
  }

  /**
   * Confirms a single-use newsletter confirmation link.
   */
  public function confirm(string $token): array {
    try {
      $result = $this->gateway->confirm($token);
    }
    catch (\InvalidArgumentException $e) {
      return $this->renderStatusPanel('Confirmation unsuccessful', $e->getMessage(), FALSE);
    }

    $smsText = $result['sms_opt_in']
      ? $this->t('Optional demo SMS updates are also enabled.')
      : $this->t('Only email updates (simulated) were requested.');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'panel' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['preview-panel'], 'role' => 'status'],
        'pill' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['demo-pill']],
          '#value' => $this->t('Subscription confirmed (demo)'),
        ],
        'summary' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Your simulated subscription is now confirmed. @sms', ['@sms' => $smsText]),
        ],
        'actions' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['nusry-receipt-actions']],
          'home' => [
            '#type' => 'link',
            '#title' => $this->t('Return to homepage'),
            '#url' => Url::fromRoute('nusry_core.homepage'),
            '#attributes' => ['class' => ['btn', 'btn--primary']],
          ],
          'unsubscribe' => [
            '#type' => 'link',
            '#title' => $this->t('Unsubscribe / withdraw consent'),
            '#url' => Url::fromUserInput($result['unsubscribe_url']),
            '#attributes' => ['class' => ['btn', 'btn--outline-dark']],
          ],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Withdraws a confirmed subscription via unsubscribe token.
   */
  public function unsubscribe(string $token): array {
    try {
      $this->gateway->unsubscribe($token);
    }
    catch (\InvalidArgumentException $e) {
      return $this->renderStatusPanel('Unsubscribe unsuccessful', $e->getMessage(), FALSE);
    }

    return $this->renderStatusPanel(
      'Unsubscribed (demo)',
      'Your simulated newsletter and SMS consent have been withdrawn.',
      TRUE,
    );
  }

  /**
   * Builds a clean accessible status panel.
   */
  private function renderStatusPanel(string $pillText, string $message, bool $success): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'panel' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['preview-panel', $success ? 'nusry-alert--info' : 'nusry-alert--warning'],
          'role' => 'status',
        ],
        'pill' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['demo-pill']],
          '#value' => $pillText,
        ],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $message,
        ],
        'back' => [
          '#type' => 'link',
          '#title' => $this->t('Back to newsletter'),
          '#url' => Url::fromRoute('nusry_forms.newsletter_page'),
          '#attributes' => ['class' => ['btn', 'btn--outline-dark']],
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
