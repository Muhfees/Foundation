<?php

namespace Drupal\nusry_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\nusry_donations\Service\DonationRepository;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Authenticated simulated donor portal with strict per-user ownership checks.
 */
final class PortalController extends ControllerBase {

  /**
   * Constructs a PortalController instance.
   */
  public function __construct(
    private readonly DonationRepository $repository,
    private readonly UserDataInterface $userData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('nusry_donations.repository'),
      $container->get('user.data'),
    );
  }

  /**
   * Displays only the signed-in demo donor's gift history & recurring plans.
   */
  public function dashboard(): array {
    $uid = (int) $this->currentUser()->id();
    $gifts = $this->repository->listByUser($uid);
    $savedMethod = (string) ($this->userData->get('nusry_portal', $uid, 'default_payment_method') ?: 'demo_card');

    $rows = [];
    foreach ($gifts as $gift) {
      $amount = $this->formatAmount((int) $gift['amount_minor'], (string) $gift['currency']);
      $planState = $gift['frequency'] === 'monthly'
        ? ((int) $gift['recurring_active'] === 1 ? 'Monthly (active demo)' : 'Monthly (paused demo)')
        : 'One-time (demo)';

      $actions = [];
      if ($gift['status'] === 'completed') {
        $actions['receipt'] = [
          'title' => $this->t('Sample receipt'),
          'url' => Url::fromRoute('nusry_portal.receipt', ['reference' => $gift['reference']]),
        ];
      }
      if ($gift['frequency'] === 'monthly' && $gift['status'] === 'completed') {
        $actions['manage'] = [
          'title' => $this->t('Manage plan'),
          'url' => Url::fromRoute('nusry_portal.recurring_edit', ['reference' => $gift['reference']]),
        ];
      }

      $rows[] = [
        $gift['reference'],
        date('Y-m-d', (int) $gift['created']),
        $amount,
        $planState,
        $gift['campaign_id'],
        $gift['status'],
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $actions,
          ],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['site-main--inner']],
      'banner' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['demo-notice-banner']],
        'pill' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['class' => ['demo-pill']],
          '#value' => $this->t('Authenticated demo portal — simulated history only'),
        ],
        'copy' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Signed in as @name. Saved simulated method: @method. Sample receipts are not valid for tax purposes.', [
            '@name' => $this->currentUser()->getDisplayName(),
            '@method' => $savedMethod,
          ]),
        ],
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['nusry-receipt-actions']],
        'donate' => [
          '#type' => 'link',
          '#title' => $this->t('Make another demo gift'),
          '#url' => Url::fromRoute('nusry_donations.donate'),
          '#attributes' => ['class' => ['btn', 'btn--primary']],
        ],
        'method' => [
          '#type' => 'link',
          '#title' => $this->t('Manage simulated payment method'),
          '#url' => Url::fromRoute('nusry_portal.payment_method'),
          '#attributes' => ['class' => ['btn', 'btn--outline-dark']],
        ],
      ],
      'table_wrap' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-scroll']],
        'table' => [
          '#type' => 'table',
          '#attributes' => ['class' => ['data-table']],
          '#header' => [
            $this->t('Reference'),
            $this->t('Date'),
            $this->t('Amount'),
            $this->t('Plan'),
            $this->t('Programme'),
            $this->t('Status'),
            $this->t('Actions'),
          ],
          '#rows' => $rows,
          '#empty' => $this->t('No simulated gifts recorded for your demo account yet.'),
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Renders a sample receipt inside the portal after ownership checks.
   */
  public function receipt(string $reference): array {
    $record = $this->repository->findByReference($reference);
    if ($record === NULL) {
      throw new NotFoundHttpException();
    }

    $uid = (int) $this->currentUser()->id();
    if ($uid < 1 || (int) $record['uid'] !== $uid || $record['status'] !== 'completed') {
      throw new AccessDeniedHttpException('You do not have permission to view this sample receipt.');
    }

    return [
      '#theme' => 'nusry_donation_receipt',
      '#receipt' => [
        'reference' => $record['reference'],
        'amount_formatted' => $this->formatAmount((int) $record['amount_minor'], (string) $record['currency']),
        'frequency' => $record['frequency'] === 'monthly' ? 'Monthly (simulated)' : 'One-time (simulated)',
        'campaign_id' => $record['campaign_id'],
        'payment_method' => $record['payment_method'],
        'tribute_type' => $record['tribute_type'],
        'tribute_name' => $record['tribute_name'],
        'employer_match' => (bool) $record['employer_match'],
        'donor_name' => $record['donor_name'],
        'created' => date('Y-m-d H:i:s T', (int) $record['created']),
        'download_url' => '/donate/receipt/' . rawurlencode($record['reference']) . '?download=1',
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Formats minor-unit currency values.
   */
  private function formatAmount(int $minor, string $currency): string {
    $symbol = match ($currency) {
      'EUR' => '€',
      'GBP' => '£',
      default => '$',
    };
    return $symbol . number_format($minor / 100, 2) . ' ' . $currency;
  }

}
