<?php

declare(strict_types=1);

namespace Drupal\Tests\nusry_donations\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\nusry_donations\Service\DonationRepository;
use Drupal\nusry_donations\Service\MockDonationGateway;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for donation validation rules.
 */
final class MockDonationGatewayUnitTest extends TestCase {

  /**
   * Ensures currency, bounds, and tribute validations fail safely.
   */
  public function testValidationRejectsInvalidAmountsCurrencyAndTribute(): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn([]);
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('node')->willReturn($storage);

    $db = $this->createMock(Connection::class);
    $time = $this->createMock(TimeInterface::class);
    $repo = new DonationRepository($db, $time);
    $gateway = new MockDonationGateway($repo, $etm);

    // Below minimum ($0.50).
    try {
      $gateway->process([
        'amount_minor' => 50,
        'currency' => 'USD',
        'frequency' => 'once',
        'campaign_id' => 'DEMO-MAT-2025',
        'donor_email' => 'supporter@example.test',
      ]);
      $this->fail('Expected InvalidArgumentException for below-min amount.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('between 1.00', $e->getMessage());
    }

    // Unsupported currency.
    try {
      $gateway->process([
        'amount_minor' => 5000,
        'currency' => 'JPY',
        'frequency' => 'once',
        'campaign_id' => 'DEMO-MAT-2025',
        'donor_email' => 'supporter@example.test',
      ]);
      $this->fail('Expected InvalidArgumentException for unsupported currency.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('Unsupported', $e->getMessage());
    }

    // Tribute without honoree name.
    try {
      $gateway->process([
        'amount_minor' => 5000,
        'currency' => 'USD',
        'frequency' => 'once',
        'campaign_id' => 'DEMO-MAT-2025',
        'tribute_type' => 'honor',
        'tribute_name' => '   ',
        'donor_email' => 'supporter@example.test',
      ]);
      $this->fail('Expected InvalidArgumentException for missing tribute honoree.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('honoree', $e->getMessage());
    }
  }

}
