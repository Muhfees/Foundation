<?php

namespace Drupal\nusry_donations\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Validates amounts, campaigns and attribution, storing simulated gifts.
 */
final class MockDonationGateway implements DonationGatewayInterface {

  /**
   * Supported demo currencies.
   */
  public const ALLOWED_CURRENCIES = ['USD', 'EUR', 'GBP'];

  /**
   * Supported simulated payment methods (no card numbers stored).
   */
  public const ALLOWED_METHODS = [
    'demo_card' => 'Simulated card checkout (no card entered)',
    'demo_wallet' => 'Simulated digital wallet',
    'demo_paypal' => 'Simulated PayPal flow',
  ];

  /**
   * Minimum and maximum allowed donation in minor units ($1 to $100,000).
   */
  public const MIN_MINOR = 100;
  public const MAX_MINOR = 10000000;

  /**
   * Constructs a MockDonationGateway instance.
   */
  public function __construct(
    private readonly DonationRepository $repository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns allowlisted active campaign IDs keyed by campaign ID.
   *
   * @return array<string, string>
   *   Campaign labels keyed by field_campaign_id.
   */
  public function getAllowlistedCampaigns(): array {
    $campaigns = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'campaign', 'status' => 1]) as $node) {
      $id = (string) ($node->get('field_campaign_id')->value ?? '');
      if ($id !== '') {
        $campaigns[$id] = $node->label();
      }
    }
    if ($campaigns === []) {
      $campaigns['DEMO-MAT-2025'] = 'Emergency Maternal Health Fund (Demo)';
    }
    return $campaigns;
  }

  /**
   * {@inheritdoc}
   */
  public function process(array $payload): array {
    $amountMinor = (int) ($payload['amount_minor'] ?? 0);
    if ($amountMinor < self::MIN_MINOR || $amountMinor > self::MAX_MINOR) {
      throw new \InvalidArgumentException('Donation amount must be between 1.00 and 100,000.00.');
    }

    $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));
    if (!in_array($currency, self::ALLOWED_CURRENCIES, TRUE)) {
      throw new \InvalidArgumentException('Unsupported demo currency.');
    }

    $frequency = (string) ($payload['frequency'] ?? 'once');
    if (!in_array($frequency, ['once', 'monthly'], TRUE)) {
      throw new \InvalidArgumentException('Frequency must be once or monthly.');
    }

    $allowedCampaigns = $this->getAllowlistedCampaigns();
    $campaignId = (string) ($payload['campaign_id'] ?? '');
    if (!isset($allowedCampaigns[$campaignId])) {
      throw new \InvalidArgumentException('Unknown or inactive campaign.');
    }

    $method = (string) ($payload['payment_method'] ?? 'demo_card');
    if (!isset(self::ALLOWED_METHODS[$method])) {
      throw new \InvalidArgumentException('Unsupported simulated payment method.');
    }

    $tributeType = (string) ($payload['tribute_type'] ?? 'none');
    if (!in_array($tributeType, ['none', 'honor', 'memory'], TRUE)) {
      throw new \InvalidArgumentException('Invalid tribute type.');
    }
    $tributeName = trim((string) ($payload['tribute_name'] ?? ''));
    if ($tributeType !== 'none' && $tributeName === '') {
      throw new \InvalidArgumentException('Enter an honoree name for a tribute gift.');
    }

    $outcome = (string) ($payload['outcome'] ?? 'completed');
    if (!in_array($outcome, ['completed', 'cancelled', 'failed'], TRUE)) {
      $outcome = 'completed';
    }

    $donorEmail = trim((string) ($payload['donor_email'] ?? 'supporter@example.test'));
    if (!filter_var($donorEmail, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Enter a valid email address.');
    }

    $record = $this->repository->createIdempotent([
      'idempotency_key' => (string) ($payload['idempotency_key'] ?? ''),
      'uid' => (int) ($payload['uid'] ?? 0),
      'donor_name' => mb_substr(trim((string) ($payload['donor_name'] ?? 'Demo Supporter')), 0, 255),
      'donor_email' => $donorEmail,
      'amount_minor' => $amountMinor,
      'currency' => $currency,
      'frequency' => $frequency,
      'campaign_id' => $campaignId,
      'tribute_type' => $tributeType,
      'tribute_name' => $tributeType === 'none' ? '' : mb_substr($tributeName, 0, 255),
      'employer_match' => !empty($payload['employer_match']),
      'payment_method' => $method,
      'status' => $outcome,
      'recurring_active' => $outcome === 'completed' && $frequency === 'monthly',
    ]);

    return [
      'status' => $record['status'],
      'reference' => $record['reference'],
      'record' => $record,
    ];
  }

}
