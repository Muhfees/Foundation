<?php

declare(strict_types=1);

namespace Nusry\Tests\Integration;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies Phase 7 security headers, open-redirect safety, and SEO demo gates.
 */
final class HardeningSecurityTest extends TestCase {

  /**
   * Booted Drupal kernel instance.
   */
  private static ?DrupalKernel $kernel = NULL;

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
   * Tests baseline hardened response headers on public responses.
   */
  public function testSecurityHeadersOnPublicResponses(): void {
    $res = self::$kernel->handle(Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => 'localhost']));
    $this->assertSame(200, $res->getStatusCode());
    $this->assertSame('noindex, nofollow', (string) $res->headers->get('X-Robots-Tag'));
    $this->assertSame('nosniff', (string) $res->headers->get('X-Content-Type-Options'));
    $this->assertSame('SAMEORIGIN', (string) $res->headers->get('X-Frame-Options'));
    $this->assertSame('strict-origin-when-cross-origin', (string) $res->headers->get('Referrer-Policy'));
    $this->assertStringContainsString('payment=()', (string) $res->headers->get('Permissions-Policy'));
    $this->assertStringContainsString("default-src 'self'", (string) $res->headers->get('Content-Security-Policy-Report-Only'));
  }

  /**
   * Tests external destination query params cannot trigger an off-site redirect.
   */
  public function testExternalDestinationDoesNotOpenRedirect(): void {
    $req = Request::create('/user/login', 'GET', ['destination' => 'https://evil.example/phish'], [], [], ['HTTP_HOST' => 'localhost']);
    $res = self::$kernel->handle($req);
    $location = (string) $res->headers->get('Location', '');
    $this->assertStringNotContainsString('evil.example', $location);
  }

}
