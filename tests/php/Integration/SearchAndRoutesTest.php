<?php

declare(strict_types=1);

namespace Nusry\Tests\Integration;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies Search API indexing, escaping, unavailable handling, RSS and sitemap.
 */
final class SearchAndRoutesTest extends TestCase {

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
   * Tests Search API published-only results, escaping and error states.
   */
  public function testSearchApiAccessEscapingAndStates(): void {
    $search = \Drupal::service('nusry_core.content_search');

    $ok = $search->search('maternal');
    $this->assertSame('ok', $ok['status']);
    $this->assertGreaterThan(0, $ok['total']);

    // Unpublished drafts must never appear in Search API results.
    $draftOnly = $search->search('Draft: Upcoming seasonal preparedness review');
    $this->assertSame(0, $draftOnly['total']);

    // Excerpt highlighter strips tags and escapes HTML entities safely.
    $markup = (string) $search->highlightExcerpt('<script>alert(1)</script> 5 < 10 & maternal clinics', 'maternal');
    $this->assertStringNotContainsString('<script>', $markup);
    $this->assertStringContainsString('5 &lt; 10 &amp;', $markup);
    $this->assertStringContainsString('<mark>maternal</mark>', $markup);

    // Oversized search keywords return validation_error.
    $tooLong = $search->search(str_repeat('a', 205));
    $this->assertSame('validation_error', $tooLong['status']);

    // Disabled index returns unavailable cleanly without throwing.
    $index = Index::load('editorial_content');
    $this->assertNotNull($index);
    $index->setStatus(FALSE)->save();
    $unavailable = $search->search('maternal');
    $this->assertSame('unavailable', $unavailable['status']);
  }

  /**
   * Tests RSS and sitemap responses exclude drafts and include published items.
   */
  public function testRssAndSitemapExcludeUnpublishedContent(): void {
    $controller = \Drupal::classResolver()->getInstanceFromDefinition(\Drupal\nusry_core\Controller\UtilityController::class);
    $request = Request::create('http://127.0.0.1:8765/rss.xml');

    $rss = $controller->rss($request);
    $this->assertSame(200, $rss->getStatusCode());
    $this->assertStringContainsString('Mobile clinics expand maternal care', (string) $rss->getContent());
    $this->assertStringNotContainsString('Draft: Upcoming seasonal preparedness', (string) $rss->getContent());

    $sitemap = $controller->sitemap($request);
    $this->assertSame(200, $sitemap->getStatusCode());
    $sitemapXml = (string) $sitemap->getContent();
    $this->assertStringContainsString('/what-we-do', $sitemapXml);
    $this->assertStringContainsString('/where-we-work', $sitemapXml);
    $this->assertStringNotContainsString('Upcoming seasonal preparedness', $sitemapXml);

    // Newly saved unpublished story is never included.
    $draft = Node::create([
      'type' => 'story',
      'title' => 'Private Secret Draft Story',
      'field_summary' => 'Hidden.',
      'field_story_kind' => 'story',
      'field_date' => '2026-11-25T09:00:00',
      'field_author' => 'Tester',
      'moderation_state' => 'draft',
      'status' => 0,
    ]);
    $draft->save();
    $this->assertStringNotContainsString('Private Secret Draft Story', (string) $controller->rss($request)->getContent());
  }

}
