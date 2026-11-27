<?php

/**
 * @file
 * Exercises real Drupal access, revisions and scheduling with disposable data.
 */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AnonymousUserSession;

if (!in_array(getenv('NUSRY_ENV'), ['local', 'ci'], TRUE)) {
  throw new RuntimeException('Workflow tests require local or CI mode.');
}
$assert = static function (bool $value, string $message): void {
  if (!$value) {
    throw new RuntimeException($message);
  }
};
$users = [];
$nodes = [];
$switcher = \Drupal::service('account_switcher');
$storage = \Drupal::entityTypeManager()->getStorage('node');
$reload = static function ($node) use ($storage) {
  $storage->resetCache([$node->id()]);
  return $storage->load($node->id());
};
$as = static function ($user, callable $callback) use ($switcher) {
  $switcher->switchTo($user);
  try { return $callback(); }
  finally { $switcher->switchBack(); }
};
try {
  foreach (['contributor', 'editor', 'donor_services', 'demo_donor'] as $role) {
    $user = User::create(['name' => 'qa_' . $role . '_' . bin2hex(random_bytes(4)), 'mail' => $role . '@example.test', 'status' => 1, 'roles' => [$role]]);
    $user->setPassword(bin2hex(random_bytes(24)))->save();
    $users[$role] = $user;
  }
  $draft = Node::create(['type' => 'flexible_page', 'title' => 'QA private draft', 'field_summary' => 'Fictional workflow test.', 'uid' => $users['contributor']->id(), 'moderation_state' => 'draft']);
  $as($users['contributor'], static function () use ($draft, $assert) {
    $assert(count($draft->validate()) === 0, (string) $draft->validate());
    $draft->save();
  });
  $nodes[] = $draft;
  $anonymous = new AnonymousUserSession();
  $assert(!$draft->access('view', $anonymous), 'Anonymous visitor can read a draft.');
  $assert(!$draft->access('view', $users['demo_donor']), 'Donor can read an editorial draft.');
  $assert(!$draft->access('update', $users['donor_services']), 'Donor services can edit content.');
  $assert($draft->access('update', $users['contributor']), 'Contributor cannot edit own draft.');
  $as($users['contributor'], static function () use ($draft, $assert) {
    $draft->set('moderation_state', 'published');
    $assert(count($draft->validate()) > 0, 'Contributor published directly.');
    $draft->set('moderation_state', 'review');
    $assert(count($draft->validate()) === 0, (string) $draft->validate());
    $draft->save();
  });
  $draft = $reload($draft);
  $as($users['editor'], static function () use ($draft, $assert) {
    $draft->set('moderation_state', 'published')->setRevisionLogMessage('QA editor approval');
    $assert(count($draft->validate()) === 0, (string) $draft->validate());
    $draft->save();
  });
  $published = $reload($draft);
  $assert($published->access('view', $anonymous), 'Approved page is not public.');
  $published_title = $published->label();
  $as($users['contributor'], static function () use ($published, $assert) {
    $published->setTitle('QA unapproved revision')->set('moderation_state', 'draft')->setRevisionLogMessage('QA contributor amendment');
    $assert(count($published->validate()) === 0, (string) $published->validate());
    $published->save();
  });
  $assert($reload($published)->label() === $published_title, 'Draft replaced the live revision.');
  $latest = $storage->loadRevision($storage->getLatestRevisionId($published->id()));
  $assert(!$latest->access('view', $anonymous), 'Anonymous can read the draft revision.');
  $as($users['contributor'], static function () use ($latest) { $latest->set('moderation_state', 'review')->save(); });
  $as($users['editor'], static function () use ($latest, $assert) {
    $latest->set('moderation_state', 'scheduled')->set('publish_on', time() + 3600)->set('publish_state', 'published');
    $assert(count($latest->validate()) === 0, (string) $latest->validate());
    $latest->save();
  });
  $assert(!$latest->access('update', $users['contributor']), 'Contributor can alter an approved scheduled revision.');
  \Drupal::service('scheduler.manager')->publish();
  $assert($reload($latest)->label() === $published_title, 'Scheduler published before the due time.');
  // Simulate the due boundary on this disposable record only; the form's future-date validation was checked above.
  $latest->set('publish_on', time() - 60)->save();
  \Drupal::service('scheduler.manager')->publish();
  $scheduled = $reload($latest);
  $assert($scheduled->isPublished() && $scheduled->label() === 'QA unapproved revision', 'Approved scheduled revision did not publish.');
  $scheduled->set('unpublish_on', time() - 30)->set('unpublish_state', 'archived')->save();
  \Drupal::service('scheduler.manager')->unpublish();
  $assert(!$reload($scheduled)->isPublished(), 'Scheduled archive failed.');
  echo "Workflow checks passed: role isolation, draft privacy, review/publish, live revision retention, scheduled edit protection, future/due publication and archive.\n";
}
finally {
  foreach ($nodes as $node) { $storage->load($node->id())?->delete(); }
  foreach ($users as $user) { $user->delete(); }
}
