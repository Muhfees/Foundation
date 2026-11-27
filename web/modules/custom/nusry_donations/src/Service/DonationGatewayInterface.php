<?php

namespace Drupal\nusry_donations\Service;

/**
 * Contract for mock and future hosted donation gateway adapters.
 */
interface DonationGatewayInterface {

  /**
   * Validates a donation request and records/initiates a mock checkout.
   *
   * @param array<string, mixed> $payload
   *   Validated donation parameters in integer minor units.
   *
   * @return array<string, mixed>
   *   Result with status, reference, and redirect route/path.
   */
  public function process(array $payload): array;

}
