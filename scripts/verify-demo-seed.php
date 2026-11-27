<?php

/**
 * @file
 * Verifies synthetic demo seed dataset and anonymous draft isolation.
 */

use Drupal\Core\Session\AnonymousUserSession;

$storage = \Drupal::entityTypeManager()->getStorage('node');
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};

$publishedStories = $storage->loadByProperties(['type' => 'story', 'status' => 1]);
$check(count($publishedStories) >= 8, 'Expected at least 8 published stories.');
foreach ($publishedStories as $story) {
  $check((int) $story->get('field_reading_time')->value >= 1, 'Expected computed reading time on story.');
}

$draftStories = $storage->loadByProperties(['type' => 'story', 'status' => 0]);
$check(count($draftStories) >= 2, 'Expected draft and scheduled workflow stories.');
$anonymous = new AnonymousUserSession();
foreach ($draftStories as $unpublished) {
  $check(!$unpublished->access('view', $anonymous), 'Anonymous users must not view unpublished stories.');
}

$check(count($storage->loadByProperties(['type' => 'country', 'status' => 1])) >= 4, 'Expected 4 illustrative countries.');
$check(count($storage->loadByProperties(['type' => 'medical_issue', 'status' => 1])) >= 4, 'Expected 4 medical issues.');
$check(count($storage->loadByProperties(['type' => 'event', 'status' => 1])) >= 3, 'Expected 3 events.');
$check(count($storage->loadByProperties(['type' => 'campaign', 'status' => 1])) >= 2, 'Expected 2 campaigns.');
$check(count($storage->loadByProperties(['type' => 'report', 'status' => 1])) >= 2, 'Expected 2 sample reports.');
$check(count($storage->loadByProperties(['type' => 'flexible_page', 'status' => 1])) >= 20, 'Expected flexible pages.');

$selection = \Drupal::service('nusry_core.story_selector')->getFeaturedAndSupporting(4);
$check($selection['featured'] !== NULL && count($selection['supporting']) === 4, 'Expected 1 featured + 4 supporting stories.');

echo "Demo seed checks passed: 8+ published stories, draft/scheduled isolation, 4 countries, 4 issues, 3 events, 2 campaigns, 2 reports, flexible pages.\n";
