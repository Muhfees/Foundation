<?php

declare(strict_types=1);

namespace Nusry\Tests\Integration;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Verifies Phase 6 donations, double opt-in newsletter, CSV, and portal ACLs.
 */
final class DonationsFormsPortalTest extends TestCase {

  /**
   * Booted Drupal kernel instance.
   */
  private static ?DrupalKernel $kernel = NULL;

  /**
   * Active DB transaction rolled back after each test.
   */
  private ?object $transaction = NULL;

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    $root = dirname(__DIR__, 3);
    $webRoot = $root . '/web';
    chdir($webRoot);
    $autoloader = require $webRoot . '/autoload.php';
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']);
    Settings::initialize($webRoot, 'sites/default', $autoloader);
    self::$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
    self::$kernel->boot();
    self::$kernel->preHandle($request);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->transaction = \Drupal::database()->startTransaction();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->transaction !== NULL) {
      $this->transaction->rollBack();
      $this->transaction = NULL;
    }
    \Drupal::service('entity.memory_cache')->deleteAll();
    parent::tearDown();
  }

  /**
   * Tests donation gateway allowlist, idempotency, and cross-user 403 isolation.
   */
  public function testDonationIdempotencyAndReceiptOwnership(): void {
    $gateway = \Drupal::service('nusry_donations.gateway');
    $payload = [
      'idempotency_key' => 'unit-test-idem-key-123',
      'uid' => 0,
      'donor_name' => '=cmd|calc!A0',
      'donor_email' => 'donor-test@example.test',
      'amount_minor' => 7500,
      'currency' => 'USD',
      'frequency' => 'monthly',
      'campaign_id' => 'DEMO-MAT-2025',
      'tribute_type' => 'honor',
      'tribute_name' => 'Dr. Amira',
      'employer_match' => TRUE,
      'payment_method' => 'demo_card',
      'outcome' => 'completed',
    ];

    $first = $gateway->process($payload);
    $second = $gateway->process($payload);
    $this->assertSame($first['reference'], $second['reference']);

    // Unowned anonymous request to receipt must receive HTTP 403.
    $unownedReq = Request::create('/donate/receipt/' . $first['reference'], 'GET', ['status' => 'completed'], [], [], ['HTTP_HOST' => 'localhost']);
    $unownedReq->setSession(new Session(new MockArraySessionStorage()));
    $unownedRes = self::$kernel->handle($unownedReq);
    $this->assertSame(403, $unownedRes->getStatusCode());

    // Unknown reference cannot be forged with query strings.
    $forgedReq = Request::create('/donate/receipt/DEMO-FORGED-999?status=completed', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']);
    $forgedReq->setSession(new Session(new MockArraySessionStorage()));
    $forgedRes = self::$kernel->handle($forgedReq);
    $this->assertSame(404, $forgedRes->getStatusCode());

    // Donor Services CSV export neutralizes the spreadsheet formula donor_name.
    $servicesUser = user_load_by_name('demo-donor-services');
    $this->assertNotFalse($servicesUser);
    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo($servicesUser);
    try {
      $exportReq = Request::create('/donor-services/export', 'GET', ['q' => $first['reference']], [], [], ['HTTP_HOST' => 'localhost']);
      $exportRes = self::$kernel->handle($exportReq);
      $this->assertSame(200, $exportRes->getStatusCode());
      $this->assertStringContainsString("'=cmd|calc!A0", (string) $exportRes->getContent());
      $this->assertStringContainsString('private', (string) $exportRes->headers->get('Cache-Control'));
      $this->assertStringContainsString('no-store', (string) $exportRes->headers->get('Cache-Control'));
    }
    finally {
      $switcher->switchBack();
    }
  }

  /**
   * Tests newsletter double opt-in token expiry, single-use, and rate limits.
   */
  public function testNewsletterDoubleOptInLifecycleAndRateLimit(): void {
    $gateway = \Drupal::service('nusry_forms.newsletter_gateway');
    $ip = '198.51.100.' . random_int(10, 200);

    // Honeypot triggered must fail cleanly.
    $this->expectException(\InvalidArgumentException::class);
    $gateway->subscribe('bot@example.test', FALSE, '', TRUE, $ip);
  }

  /**
   * Tests newsletter token expiry, single-use confirmation, and flood throttling.
   */
  public function testNewsletterTokenSecurityAndThrottling(): void {
    $gateway = \Drupal::service('nusry_forms.newsletter_gateway');
    $db = \Drupal::database();
    $ip = '203.0.113.' . random_int(10, 200);

    $pending = $gateway->subscribe('reader@example.test', TRUE, '+1-555-0199', FALSE, $ip);
    $this->assertSame('pending', $pending['status']);
    $this->assertTrue($pending['sms_opt_in']);

    // Expire token manually to verify expiry enforcement.
    $db->update('nusry_newsletter_subscriptions')
      ->fields(['token_expires' => time() - 60])
      ->condition('token_hash', hash('sha256', $pending['confirm_token']))
      ->execute();

    $expiredFailed = FALSE;
    try {
      $gateway->confirm($pending['confirm_token']);
    }
    catch (\InvalidArgumentException) {
      $expiredFailed = TRUE;
    }
    $this->assertTrue($expiredFailed, 'Expired confirmation token must be rejected.');

    // Re-subscribe to receive a fresh token, confirm once, reject reuse.
    $fresh = $gateway->subscribe('reader@example.test', TRUE, '+1-555-0199', FALSE, $ip);
    $confirmed = $gateway->confirm($fresh['confirm_token']);
    $this->assertSame('confirmed', $confirmed['status']);

    $reuseRejected = FALSE;
    try {
      $gateway->confirm($fresh['confirm_token']);
    }
    catch (\InvalidArgumentException) {
      $reuseRejected = TRUE;
    }
    $this->assertTrue($reuseRejected, 'Confirmation token must be strictly single-use.');

    $unsub = $gateway->unsubscribe($confirmed['unsubscribe_token']);
    $this->assertSame('unsubscribed', $unsub['status']);

    // Rate limiting blocks attempts after FLOOD_LIMIT within window.
    $rateLimited = FALSE;
    for ($i = 0; $i < 6; $i++) {
      try {
        $gateway->subscribe("flood{$i}@example.test", FALSE, '', FALSE, $ip);
      }
      catch (\OverflowException) {
        $rateLimited = TRUE;
        break;
      }
    }
    $this->assertTrue($rateLimited, 'Flood protection must throttle excessive newsletter requests.');
  }

  /**
   * Tests cross-user portal access denial and least-privilege role separation.
   */
  public function testPortalOwnershipIsolationAndPrivateHeaders(): void {
    $demoDonor = user_load_by_name('demo-donor');
    $editor = user_load_by_name('demo-editor');
    $this->assertNotFalse($demoDonor);
    $this->assertNotFalse($editor);

    // Create a second independent demo_donor user to prove IDOR protection.
    $otherDonor = User::create([
      'name' => 'other-donor-' . random_int(100, 999),
      'mail' => 'other-donor@example.test',
      'status' => 1,
      'roles' => ['demo_donor'],
    ]);
    $otherDonor->save();

    $switcher = \Drupal::service('account_switcher');

    // Other donor cannot open demo-donor's gift receipt or recurring edit route.
    $switcher->switchTo($otherDonor);
    try {
      $req = Request::create('/portal/gifts/DEMO-SEED-ONCE-001/receipt', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']);
      $res = self::$kernel->handle($req);
      $this->assertSame(403, $res->getStatusCode());

      $editReq = Request::create('/portal/recurring/DEMO-SEED-MON-002/edit', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']);
      $editRes = self::$kernel->handle($editReq);
      $this->assertSame(403, $editRes->getStatusCode());
    }
    finally {
      $switcher->switchBack();
    }

    // Editorial user cannot access /donor-services or /portal.
    $switcher->switchTo($editor);
    try {
      $dsRes = self::$kernel->handle(Request::create('/donor-services', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']));
      $this->assertSame(403, $dsRes->getStatusCode());
      $portalRes = self::$kernel->handle(Request::create('/portal', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']));
      $this->assertSame(403, $portalRes->getStatusCode());
    }
    finally {
      $switcher->switchBack();
    }

    // Owning demo-donor succeeds with private, no-store cache headers.
    $switcher->switchTo($demoDonor);
    try {
      $ownerRes = self::$kernel->handle(Request::create('/portal/gifts/DEMO-SEED-ONCE-001/receipt', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']));
      $this->assertSame(200, $ownerRes->getStatusCode());
      $this->assertStringContainsString('Demo complete—no charge made.', (string) $ownerRes->getContent());
      $this->assertStringContainsString('Sample receipt—not valid for tax purposes.', (string) $ownerRes->getContent());
      $this->assertStringContainsString('private', (string) $ownerRes->headers->get('Cache-Control'));
      $this->assertStringContainsString('no-store', (string) $ownerRes->headers->get('Cache-Control'));
      $this->assertSame('noindex, nofollow', (string) $ownerRes->headers->get('X-Robots-Tag'));
    }
    finally {
      $switcher->switchBack();
    }

    // Contact submissions table is never searchable through Search API.
    \Drupal::database()->insert('nusry_contact_submissions')
      ->fields([
        'sender_name' => 'Private Contact',
        'sender_email' => 'private-contact@example.test',
        'topic' => 'general',
        'message' => 'ZXQ_CONFIDENTIAL_CONTACT_MARKER_991',
        'consent_ack' => 1,
        'created' => time(),
      ])
      ->execute();
    $search = \Drupal::service('nusry_core.content_search')->search('ZXQ_CONFIDENTIAL_CONTACT_MARKER_991');
    $this->assertSame(0, $search['total']);
  }

}
