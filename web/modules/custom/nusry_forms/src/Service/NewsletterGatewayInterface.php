<?php

namespace Drupal\nusry_forms\Service;

/**
 * Contract for simulated double opt-in newsletter gateways.
 */
interface NewsletterGatewayInterface {

  /**
   * Registers or refreshes a pending double opt-in subscription.
   *
   * @return array<string, mixed>
   *   Pending result including demo confirmation path.
   */
  public function subscribe(
    string $email,
    bool $smsOptIn = FALSE,
    string $phone = '',
    bool $honeypotTriggered = FALSE,
    string $clientIp = '127.0.0.1',
  ): array;

  /**
   * Confirms a single-use, unexpired subscription token.
   *
   * @return array<string, mixed>
   *   Confirmed state and unsubscribe path.
   */
  public function confirm(string $rawToken): array;

  /**
   * Withdraws a subscription via its dedicated unsubscribe token.
   *
   * @return array<string, mixed>
   *   Unsubscribed status payload.
   */
  public function unsubscribe(string $rawToken): array;

}
