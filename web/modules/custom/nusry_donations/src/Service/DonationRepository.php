<?php

namespace Drupal\nusry_donations\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Persists and queries synthetic demo donation records safely.
 */
final class DonationRepository {

  /**
   * Constructs a DonationRepository instance.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Finds an existing donation by idempotency key.
   */
  public function findByIdempotencyKey(string $key): ?array {
    if ($key === '') {
      return NULL;
    }
    $record = $this->database->select('nusry_donations', 'd')
      ->fields('d')
      ->condition('idempotency_key', $key)
      ->execute()
      ->fetchAssoc();
    return $record ?: NULL;
  }

  /**
   * Finds a donation record by reference code.
   */
  public function findByReference(string $reference): ?array {
    $record = $this->database->select('nusry_donations', 'd')
      ->fields('d')
      ->condition('reference', $reference)
      ->execute()
      ->fetchAssoc();
    return $record ?: NULL;
  }

  /**
   * Inserts a synthetic donation idempotently.
   *
   * @param array<string, mixed> $data
   *   Donation row values.
   *
   * @return array<string, mixed>
   *   Inserted or existing idempotent record.
   */
  public function createIdempotent(array $data): array {
    $idempotencyKey = (string) ($data['idempotency_key'] ?? '');
    if ($idempotencyKey !== '') {
      $existing = $this->findByIdempotencyKey($idempotencyKey);
      if ($existing !== NULL) {
        return $existing;
      }
    }

    $now = $this->time->getRequestTime();
    $reference = (string) ($data['reference'] ?? ('DEMO-' . gmdate('Ymd', $now) . '-' . strtoupper(bin2hex(random_bytes(3)))));
    $row = [
      'reference' => $reference,
      'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : NULL,
      'uid' => (int) ($data['uid'] ?? 0),
      'donor_name' => (string) ($data['donor_name'] ?? 'Demo Supporter'),
      'donor_email' => (string) ($data['donor_email'] ?? 'supporter@example.test'),
      'amount_minor' => (int) $data['amount_minor'],
      'currency' => (string) ($data['currency'] ?? 'USD'),
      'frequency' => (string) ($data['frequency'] ?? 'once'),
      'campaign_id' => (string) ($data['campaign_id'] ?? 'DEMO-MAT-2025'),
      'tribute_type' => (string) ($data['tribute_type'] ?? 'none'),
      'tribute_name' => (string) ($data['tribute_name'] ?? ''),
      'employer_match' => !empty($data['employer_match']) ? 1 : 0,
      'payment_method' => (string) ($data['payment_method'] ?? 'demo_card'),
      'status' => (string) ($data['status'] ?? 'completed'),
      'recurring_active' => !empty($data['recurring_active']) ? 1 : 0,
      'created' => $now,
      'updated' => $now,
    ];
    $this->database->insert('nusry_donations')->fields($row)->execute();
    return $this->findByReference($reference) ?? $row;
  }

  /**
   * Lists synthetic donations owned by a specific user ID.
   *
   * @return array<int, array<string, mixed>>
   *   Ordered donation rows newest-first.
   */
  public function listByUser(int $uid): array {
    if ($uid < 1) {
      return [];
    }
    return $this->database->select('nusry_donations', 'd')
      ->fields('d')
      ->condition('uid', $uid)
      ->orderBy('created', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Lists recent synthetic donations for authorized donor-services lookup.
   *
   * @return array<int, array<string, mixed>>
   *   Recent synthetic donation rows.
   */
  public function listRecent(int $limit = 50): array {
    return $this->database->select('nusry_donations', 'd')
      ->fields('d')
      ->orderBy('created', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, max(1, min(100, $limit)))
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * Updates a user's simulated recurring gift parameters.
   */
  public function updateRecurring(string $reference, int $uid, int $amountMinor, string $frequency, bool $active): bool {
    $existing = $this->findByReference($reference);
    if ($existing === NULL || (int) $existing['uid'] !== $uid) {
      return FALSE;
    }
    $this->database->update('nusry_donations')
      ->fields([
        'amount_minor' => $amountMinor,
        'frequency' => $frequency,
        'recurring_active' => $active ? 1 : 0,
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('reference', $reference)
      ->condition('uid', $uid)
      ->execute();
    return TRUE;
  }

  /**
   * Updates payment method on active recurring gifts owned by a user.
   */
  public function updatePaymentMethodByUser(int $uid, string $paymentMethod): int {
    if ($uid < 1) {
      return 0;
    }
    return (int) $this->database->update('nusry_donations')
      ->fields([
        'payment_method' => $paymentMethod,
        'updated' => $this->time->getRequestTime(),
      ])
      ->condition('uid', $uid)
      ->condition('frequency', 'monthly')
      ->execute();
  }

}
