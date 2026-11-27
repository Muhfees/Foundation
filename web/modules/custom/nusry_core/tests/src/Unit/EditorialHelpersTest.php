<?php

declare(strict_types=1);

namespace Drupal\Tests\nusry_core\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\nusry_core\Service\EditorialSettings;
use Drupal\nusry_core\Service\ReadingTimeCalculator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests reading time estimation and editorial settings validation.
 */
#[Group('nusry_core')]
final class EditorialHelpersTest extends TestCase {

  /**
   * Tests deterministic reading time calculation.
   */
  public function testReadingTimeCalculation(): void {
    $calculator = new ReadingTimeCalculator();
    $this->assertSame(1, $calculator->calculateFromText(''));
    $this->assertSame(1, $calculator->calculateFromText('<p>Short clinic update.</p>'));
    $words = implode(' ', array_fill(0, 401, 'medical'));
    $this->assertSame(3, $calculator->calculateFromText("<p>$words</p>"));

    $this->expectException(\InvalidArgumentException::class);
    $calculator->calculateFromText('Text', 0);
  }

  /**
   * Tests fund allocation validation and alert window checks.
   */
  public function testEditorialSettingsValidationAndAlertWindow(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      [
        'fund_allocation',
        [
          'period' => '2025 Demo',
          'source_label' => 'Demo annual report',
          'programmes_percent' => 82,
          'fundraising_percent' => 12,
          'administration_percent' => 6,
        ],
      ],
      [
        'urgent_alert',
        [
          'enabled' => TRUE,
          'text' => 'Demo alert',
          'starts' => '2026-01-01T00:00:00Z',
          'ends' => '2026-02-01T00:00:00Z',
          'version' => 'v1',
        ],
      ],
    ]);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('nusry_core.settings')->willReturn($config);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(strtotime('2026-01-15T00:00:00Z'));

    $settings = new EditorialSettings($factory, $time);
    $allocation = $settings->getFundAllocation();
    $this->assertSame(82, $allocation['programmes_percent']);
    $this->assertNotNull($settings->getActiveUrgentAlert());
    $this->assertNull($settings->getActiveUrgentAlert(strtotime('2025-12-31T23:59:59Z')));
    $this->assertNull($settings->getActiveUrgentAlert(strtotime('2026-02-01T00:00:01Z')));

    $this->expectException(\InvalidArgumentException::class);
    $settings->validateFundAllocation(80, 10, 5);
  }

}
