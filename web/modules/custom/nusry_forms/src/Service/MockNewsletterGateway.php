<?php

namespace Drupal\nusry_forms\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Flood\FloodInterface;
use Psr\Log\LoggerInterface;

/**
 * Simulates double opt-in newsletter & optional SMS subscriptions.
 */
final class MockNewsletterGateway implements NewsletterGatewayInterface {

  /**
   * Token lifetime in seconds (24 hours).
   */
  public const TOKEN_TTL = 86400;

  /**
   * Flood limit per window.
   */
  public const FLOOD_LIMIT = 5;

  /**
   * Flood window in seconds (15 minutes).
   */
  public const FLOOD_WINDOW = 900;

  /**
   * Constructs a MockNewsletterGateway instance.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly FloodInterface $flood,
    private readonly ?FileSystemInterface $fileSystem,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function subscribe(
    string $email,
    bool $smsOptIn = FALSE,
    string $phone = '',
    bool $honeypotTriggered = FALSE,
    string $clientIp = '127.0.0.1',
  ): array {
    if ($honeypotTriggered) {
      $this->logger->notice('Honeypot blocked automated newsletter submission.');
      throw new \InvalidArgumentException('Submission could not be accepted.');
    }

    if (!$this->flood->isAllowed('nusry_forms.newsletter_subscribe', self::FLOOD_LIMIT, self::FLOOD_WINDOW, $clientIp)) {
      $this->logger->warning('Rate limit reached for newsletter subscribe.');
      throw new \OverflowException('Too many subscription attempts. Please wait a few minutes and try again.');
    }
    $this->flood->register('nusry_forms.newsletter_subscribe', self::FLOOD_WINDOW, $clientIp);

    $normalizedEmail = mb_strtolower(trim($email));
    if ($normalizedEmail === '' || mb_strlen($normalizedEmail) > 254 || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Enter a valid email address.');
    }

    $cleanPhone = trim($phone);
    if ($smsOptIn) {
      if ($cleanPhone === '' || preg_match('/^[+0-9()\-\s]{7,24}$/', $cleanPhone) !== 1) {
        throw new \InvalidArgumentException('Enter a valid demo phone number (7 to 24 characters) when SMS updates are enabled.');
      }
    }
    else {
      $cleanPhone = '';
    }

    $now = $this->time->getRequestTime();
    $rawToken = $this->generateToken();
    $tokenHash = hash('sha256', $rawToken);
    $emailHash = hash('sha256', $normalizedEmail);

    $existing = $this->database->select('nusry_newsletter_subscriptions', 'n')
      ->fields('n', ['id'])
      ->condition('email_hash', $emailHash)
      ->execute()
      ->fetchField();

    $fields = [
      'email' => $normalizedEmail,
      'email_hash' => $emailHash,
      'sms_opt_in' => $smsOptIn ? 1 : 0,
      'phone' => mb_substr($cleanPhone, 0, 32),
      'status' => 'pending',
      'token_hash' => $tokenHash,
      'token_expires' => $now + self::TOKEN_TTL,
      'unsub_token_hash' => '',
      'unsubscribed' => 0,
    ];

    if ($existing) {
      $this->database->update('nusry_newsletter_subscriptions')
        ->fields($fields)
        ->condition('id', (int) $existing)
        ->execute();
    }
    else {
      $fields['created'] = $now;
      $fields['confirmed'] = 0;
      $this->database->insert('nusry_newsletter_subscriptions')
        ->fields($fields)
        ->execute();
    }

    $confirmPath = '/newsletter/confirm/' . $rawToken;
    $this->writeDemoMailCatcher($confirmPath);
    $this->logger->notice('Demo newsletter confirmation token generated.');

    return [
      'status' => 'pending',
      'sms_opt_in' => $smsOptIn,
      'confirm_token' => $rawToken,
      'demo_confirm_url' => $confirmPath,
      'expires' => $now + self::TOKEN_TTL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function confirm(string $rawToken): array {
    $cleanToken = trim($rawToken);
    if ($cleanToken === '') {
      throw new \InvalidArgumentException('Confirmation token is missing.');
    }

    $tokenHash = hash('sha256', $cleanToken);
    $row = $this->database->select('nusry_newsletter_subscriptions', 'n')
      ->fields('n')
      ->condition('token_hash', $tokenHash)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      throw new \InvalidArgumentException('This confirmation link is invalid or has already been used.');
    }

    $now = $this->time->getRequestTime();
    if ((int) $row['token_expires'] < $now) {
      throw new \InvalidArgumentException('This confirmation link has expired. Please subscribe again.');
    }

    $unsubToken = $this->generateToken();
    $this->database->update('nusry_newsletter_subscriptions')
      ->fields([
        'status' => 'confirmed',
        'confirmed' => $now,
        'token_hash' => '',
        'token_expires' => 0,
        'unsub_token_hash' => hash('sha256', $unsubToken),
        'unsubscribed' => 0,
      ])
      ->condition('id', (int) $row['id'])
      ->execute();

    $this->logger->notice('Demo newsletter subscription confirmed.');

    return [
      'status' => 'confirmed',
      'sms_opt_in' => (bool) $row['sms_opt_in'],
      'unsubscribe_token' => $unsubToken,
      'unsubscribe_url' => '/newsletter/unsubscribe/' . $unsubToken,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe(string $rawToken): array {
    $cleanToken = trim($rawToken);
    if ($cleanToken === '') {
      throw new \InvalidArgumentException('Unsubscribe token is missing.');
    }

    $row = $this->database->select('nusry_newsletter_subscriptions', 'n')
      ->fields('n', ['id'])
      ->condition('unsub_token_hash', hash('sha256', $cleanToken))
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      throw new \InvalidArgumentException('Invalid or already completed unsubscribe link.');
    }

    $now = $this->time->getRequestTime();
    $this->database->update('nusry_newsletter_subscriptions')
      ->fields([
        'status' => 'unsubscribed',
        'unsubscribed' => $now,
        'unsub_token_hash' => '',
        'sms_opt_in' => 0,
      ])
      ->condition('id', (int) $row['id'])
      ->execute();

    $this->logger->notice('Demo newsletter consent withdrawn.');

    return ['status' => 'unsubscribed'];
  }

  /**
   * Generates a URL-safe random token.
   */
  private function generateToken(): string {
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
  }

  /**
   * Captures a non-PII simulated confirmation link inside private storage.
   */
  private function writeDemoMailCatcher(string $confirmPath): void {
    if ($this->fileSystem === NULL) {
      return;
    }
    try {
      $dir = 'private://demo-mail';
      if (!$this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        return;
      }
      $filename = sprintf('%s/newsletter-%d-%s.txt', $dir, $this->time->getRequestTime(), bin2hex(random_bytes(3)));
      $body = implode("\n", [
        'NUSRY FOUNDATION — DEMO MAIL CATCHER (NO REAL EMAIL SENT)',
        'Confirmation path: ' . $confirmPath,
        'Expires in 24 hours. Single-use token.',
      ]) . "\n";
      $this->fileSystem->saveData($body, $filename, FileExists::Replace);
    }
    catch (\Throwable) {
      // Ignore stream wrapper failures in isolated test harnesses.
    }
  }

}
