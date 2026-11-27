<?php

declare(strict_types=1);

namespace Nusry\Tests\Integration;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Verifies roles, moderation, scheduling, validation, redirects and selectors.
 */
final class EditorialWorkflowAndRolesTest extends TestCase {

  /**
   * Booted Drupal kernel instance.
   */
  private static ?DrupalKernel $kernel = NULL;

  /**
   * Active database transaction rolled back after each test.
   */
  private ?object $transaction = NULL;

  /**
   * Temporary file paths created during a test.
   *
   * @var string[]
   */
  private array $tempFiles = [];

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
    foreach ($this->tempFiles as $path) {
      if (is_file($path)) {
        @unlink($path);
      }
    }
    $this->tempFiles = [];
    \Drupal::service('entity.memory_cache')->deleteAll();
    parent::tearDown();
  }

  /**
   * Tests least-privilege role permissions and draft/revision access rules.
   */
  public function testLeastPrivilegeRolesAndModerationAccess(): void {
    $contributorRole = Role::load('contributor');
    $editorRole = Role::load('editor');
    $donorServicesRole = Role::load('donor_services');
    $demoDonorRole = Role::load('demo_donor');

    $this->assertTrue($contributorRole->hasPermission('create story content'));
    $this->assertTrue($contributorRole->hasPermission('use editorial transition submit_review'));
    $this->assertFalse($contributorRole->hasPermission('use editorial transition publish'));
    $this->assertFalse($contributorRole->hasPermission('delete any story content'));
    $this->assertFalse($contributorRole->hasPermission('administer permissions'));

    $this->assertTrue($editorRole->hasPermission('use editorial transition publish'));
    $this->assertTrue($editorRole->hasPermission('schedule publishing of nodes'));
    $this->assertFalse($editorRole->hasPermission('administer modules'));
    $this->assertFalse($editorRole->hasPermission('administer users'));

    foreach ([$donorServicesRole, $demoDonorRole] as $nonEditorialRole) {
      $this->assertFalse($nonEditorialRole->hasPermission('create story content'));
      $this->assertFalse($nonEditorialRole->hasPermission('use editorial transition publish'));
      $this->assertFalse($nonEditorialRole->hasPermission('access content overview'));
    }

    $suffix = bin2hex(random_bytes(4));
    $contributorA = User::create(['name' => "contrib-a-$suffix", 'mail' => "ca-$suffix@example.test", 'status' => 1, 'roles' => ['contributor']]);
    $contributorA->save();
    $contributorB = User::create(['name' => "contrib-b-$suffix", 'mail' => "cb-$suffix@example.test", 'status' => 1, 'roles' => ['contributor']]);
    $contributorB->save();
    $editor = User::create(['name' => "editor-a-$suffix", 'mail' => "ed-$suffix@example.test", 'status' => 1, 'roles' => ['editor']]);
    $editor->save();

    $image = $this->createImageMedia($editor->id());

    $draft = Node::create([
      'type' => 'story',
      'title' => "Contributor draft $suffix",
      'uid' => $contributorA->id(),
      'field_summary' => 'Draft summary.',
      'field_story_kind' => 'story',
      'field_date' => '2026-10-01T09:00:00',
      'field_author' => 'Contributor A',
      'field_hero' => ['target_id' => $image->id()],
      'moderation_state' => 'draft',
    ]);
    $draft->save();

    $anonymous = new AnonymousUserSession();
    $this->assertFalse($draft->access('view', $anonymous));
    $this->assertTrue($draft->access('update', $contributorA));
    $this->assertFalse($draft->access('update', $contributorB));
    $this->assertTrue($draft->access('update', $editor));

    // Editor publishes the story; anonymous users can now view it.
    $draft->set('moderation_state', 'published');
    $draft->save();
    $published = Node::load($draft->id());
    $this->assertTrue($published->isPublished());
    $this->assertTrue($published->access('view', $anonymous));

    // Pending draft revision leaves published default revision intact.
    $pending = clone $published;
    $pending->setNewRevision(TRUE);
    $pending->isDefaultRevision(FALSE);
    $pending->setTitle("Unpublished revision $suffix");
    $pending->set('moderation_state', 'draft');
    $pending->save();

    \Drupal::entityTypeManager()->getStorage('node')->resetCache([(int) $published->id()]);
    $liveReloaded = Node::load($published->id());
    $this->assertSame("Contributor draft $suffix", $liveReloaded->label());
    $this->assertTrue($liveReloaded->isPublished());

    // Archiving removes public access.
    $liveReloaded->setNewRevision(TRUE);
    $liveReloaded->set('moderation_state', 'archived');
    $liveReloaded->save();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([(int) $published->id()]);
    $archived = Node::load($published->id());
    $this->assertFalse($archived->isPublished());
    $this->assertFalse($archived->access('view', $anonymous));
  }

  /**
   * Tests scheduled transitions, constraints, StorySelector and redirects.
   */
  public function testSchedulingValidationSelectorAndRedirects(): void {
    $suffix = bin2hex(random_bytes(4));
    $editor = User::create(['name' => "sched-ed-$suffix", 'mail' => "se-$suffix@example.test", 'status' => 1, 'roles' => ['editor']]);
    $editor->save();
    $image = $this->createImageMedia($editor->id());

    $scheduled = Node::create([
      'type' => 'story',
      'title' => "Scheduled update $suffix",
      'uid' => $editor->id(),
      'field_summary' => 'Scheduled summary.',
      'field_body' => ['value' => '<p>' . str_repeat('care ', 250) . '</p>', 'format' => 'restricted_html'],
      'field_story_kind' => 'news',
      'field_date' => '2030-10-12T09:00:00',
      'field_author' => 'Editor',
      'field_hero' => ['target_id' => $image->id()],
      'moderation_state' => 'scheduled',
      'publish_on' => time() - 60,
      'publish_state' => 'published',
    ]);
    $scheduled->save();
    $this->assertFalse(Node::load($scheduled->id())->isPublished());

    \Drupal::service('scheduler.manager')->publish();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([(int) $scheduled->id()]);
    $nowPublished = Node::load($scheduled->id());
    $this->assertTrue($nowPublished->isPublished());
    $this->assertSame('published', $nowPublished->get('moderation_state')->value);
    $this->assertGreaterThanOrEqual(2, (int) $nowPublished->get('field_reading_time')->value);

    // Renaming a published node alias creates a 301 redirect.
    $oldAlias = ltrim(\Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $nowPublished->id()), '/');
    $this->assertStringStartsWith('stories/', $oldAlias);
    $nowPublished->setTitle("Renamed update $suffix");
    $nowPublished->save();
    $redirects = \Drupal::service('redirect.repository')->findBySourcePath($oldAlias);
    $this->assertNotEmpty($redirects);

    // Unpublish scheduling transitions published node to archived.
    $nowPublished->set('unpublish_on', time() - 60);
    $nowPublished->set('unpublish_state', 'archived');
    $nowPublished->save();
    \Drupal::service('scheduler.manager')->unpublish();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([(int) $scheduled->id()]);
    $nowArchived = Node::load($scheduled->id());
    $this->assertFalse($nowArchived->isPublished());
    $this->assertSame('archived', $nowArchived->get('moderation_state')->value);

    // StorySelector excludes archived stories and deduplicates featured story.
    $story1 = Node::create([
      'type' => 'story',
      'title' => "Newest test story $suffix",
      'uid' => $editor->id(),
      'field_summary' => 'Summary one.',
      'field_story_kind' => 'story',
      'field_date' => '2035-10-14T09:00:00',
      'field_author' => 'Author One',
      'field_hero' => ['target_id' => $image->id()],
      'moderation_state' => 'published',
    ]);
    $story1->save();
    $story2 = Node::create([
      'type' => 'story',
      'title' => "Second test story $suffix",
      'uid' => $editor->id(),
      'field_summary' => 'Summary two.',
      'field_story_kind' => 'story',
      'field_date' => '2035-10-10T09:00:00',
      'field_author' => 'Author Two',
      'field_hero' => ['target_id' => $image->id()],
      'moderation_state' => 'published',
    ]);
    $story2->save();

    $selected = \Drupal::service('nusry_core.story_selector')->getFeaturedAndSupporting(4);
    $this->assertSame((int) $story1->id(), (int) $selected['featured']->id());
    $supportingIds = array_map(static fn(Node $n) => (int) $n->id(), $selected['supporting']);
    $this->assertContains((int) $story2->id(), $supportingIds);
    $this->assertNotContains((int) $story1->id(), $supportingIds);
    $this->assertNotContains((int) $nowArchived->id(), $supportingIds);

    // Switch to editor account so moderation_state validation checks editor permissions.
    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo($editor);
    try {
      // Editorial constraint rejects invalid event end date and timezone.
      $badEvent = Node::create([
        'type' => 'event',
        'title' => 'Invalid event',
        'field_summary' => 'Test summary.',
        'field_start' => '2026-12-10T15:00:00',
        'field_end' => '2026-12-10T14:00:00',
        'field_timezone' => 'Invalid/Zone',
        'field_accessibility' => 'Captions provided.',
      ]);
      $this->assertGreaterThanOrEqual(2, $badEvent->validate()->count());

      // Media alt-or-decorative constraint rejects non-decorative image without alt.
      $noAlt = Media::create([
        'bundle' => 'image',
        'name' => 'Missing alt',
        'uid' => $editor->id(),
        'field_media_image' => ['target_id' => $image->get('field_media_image')->target_id, 'alt' => ''],
        'field_decorative' => 0,
        'field_credit' => 'Demo',
        'field_license' => 'CC0',
        'field_provenance' => 'Demo',
        'field_source_url' => ['uri' => 'https://example.test/img.png'],
      ]);
      $this->assertGreaterThan(0, $noAlt->validate()->count());
      $noAlt->set('field_decorative', 1);
      $noAltViolations = $noAlt->validate();
      $this->assertSame(0, $noAltViolations->count(), (string) $noAltViolations);

      // Sensitive media cannot be placed inside non-sensitive hero paragraph.
      $sensitive = $this->createImageMedia($editor->id(), TRUE);
      $hero = Paragraph::create(['type' => 'hero', 'field_heading' => 'H1', 'field_media' => ['target_id' => $sensitive->id()]]);
      $this->assertGreaterThan(0, $hero->validate()->count());
    }
    finally {
      $switcher->switchBack();
    }
  }

  /**
   * Creates a published local image media entity for tests.
   */
  private function createImageMedia(int|string $uid, bool $sensitive = FALSE): Media {
    $filename = 'test-' . bin2hex(random_bytes(4)) . '.png';
    $dir = \Drupal::service('file_system')->realpath('private://');
    $path = $dir . '/' . $filename;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    file_put_contents($path, $png);
    $this->tempFiles[] = $path;
    $file = File::create(['uri' => 'private://' . $filename, 'status' => 1]);
    $file->save();
    $media = Media::create([
      'bundle' => 'image',
      'name' => "Test image $filename",
      'uid' => $uid,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => 'Descriptive demo alt text'],
      'field_decorative' => 0,
      'field_sensitive' => $sensitive ? 1 : 0,
      'field_credit' => 'Demo credit',
      'field_license' => 'CC0-1.0',
      'field_provenance' => 'Generated for PHPUnit integration test',
      'field_source_url' => ['uri' => 'https://example.test/test.png'],
      'moderation_state' => 'published',
      'status' => 1,
    ]);
    $media->save();
    return $media;
  }

}
