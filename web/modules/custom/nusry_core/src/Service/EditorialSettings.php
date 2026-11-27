<?php

namespace Drupal\nusry_core\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides validated reusable editorial settings and alert windows.
 */
final class EditorialSettings {

  /**
   * Constructs an EditorialSettings service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Validates that fund allocation percentages are non-negative and total 100.
   */
  public function validateFundAllocation(int $programmes, int $fundraising, int $administration): void {
    if ($programmes < 0 || $fundraising < 0 || $administration < 0) {
      throw new \InvalidArgumentException('Fund allocation percentages cannot be negative.');
    }
    if (($programmes + $fundraising + $administration) !== 100) {
      throw new \InvalidArgumentException('Fund allocation percentages must sum to 100.');
    }
  }

  /**
   * Returns the validated fund allocation dataset.
   *
   * @return array{period: string, source_label: string, programmes_percent: int, fundraising_percent: int, administration_percent: int}
   *   Fund allocation settings.
   */
  public function getFundAllocation(): array {
    $data = (array) $this->configFactory->get('nusry_core.settings')->get('fund_allocation');
    $programmes = (int) ($data['programmes_percent'] ?? 0);
    $fundraising = (int) ($data['fundraising_percent'] ?? 0);
    $administration = (int) ($data['administration_percent'] ?? 0);
    $this->validateFundAllocation($programmes, $fundraising, $administration);
    return [
      'period' => (string) ($data['period'] ?? ''),
      'source_label' => (string) ($data['source_label'] ?? ''),
      'programmes_percent' => $programmes,
      'fundraising_percent' => $fundraising,
      'administration_percent' => $administration,
    ];
  }

  /**
   * Returns the urgent alert if enabled and within its active time window.
   */
  public function getActiveUrgentAlert(?int $timestamp = NULL): ?array {
    $alert = (array) $this->configFactory->get('nusry_core.settings')->get('urgent_alert');
    if (empty($alert['enabled'])) {
      return NULL;
    }
    $now = $timestamp ?? $this->time->getRequestTime();
    if (!empty($alert['starts'])) {
      $starts = strtotime((string) $alert['starts']);
      if ($starts !== FALSE && $now < $starts) {
        return NULL;
      }
    }
    if (!empty($alert['ends'])) {
      $ends = strtotime((string) $alert['ends']);
      if ($ends !== FALSE && $now > $ends) {
        return NULL;
      }
    }
    return $alert;
  }

  /**
   * Returns the configured organization facts dataset.
   */
  public function getOrganizationFacts(): array {
    return (array) $this->configFactory->get('nusry_core.settings')->get('organization_facts');
  }

}
