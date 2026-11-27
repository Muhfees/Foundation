<?php

/**
 * @file
 * Rehearses local SQLite backup, isolated restoration, row verification, and cleanup.
 */

declare(strict_types=1);

if (!in_array(getenv('NUSRY_ENV'), ['local', 'ci'], TRUE)) {
  throw new RuntimeException('Backup/restore rehearsal is restricted to explicit local or CI environments.');
}

$root = dirname(__DIR__);
$sourceDb = $root . '/.local/site.sqlite';
if (!is_file($sourceDb)) {
  throw new RuntimeException('Local SQLite database not found at .local/site.sqlite');
}

$backupDir = $root . '/.local/backups';
$restoreDir = $root . '/.local/restore-rehearsal';
foreach ([$backupDir, $restoreDir] as $dir) {
  if (!is_dir($dir)) {
    mkdir($dir, 0700, TRUE);
  }
}

$stamp = gmdate('Ymd-His');
$snapshotPath = $backupDir . "/site-{$stamp}.sqlite";
$restoredPath = $restoreDir . "/restored-{$stamp}.sqlite";
@unlink($snapshotPath);
@unlink($restoredPath);

$registerCollation = static function (PDO $pdo): void {
  $pdo->sqliteCreateCollation('NOCASE_UTF8', static fn(?string $a, ?string $b): int => strcmp(mb_strtolower((string) $a), mb_strtolower((string) $b)));
};

$srcPdo = new PDO('sqlite:' . $sourceDb);
$srcPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$registerCollation($srcPdo);
// Consistent online snapshot outside public webroot.
$escapedSnapshot = str_replace("'", "''", $snapshotPath);
$srcPdo->exec("VACUUM INTO '{$escapedSnapshot}'");
$srcPdo = NULL;

if (!copy($snapshotPath, $restoredPath)) {
  throw new RuntimeException('Failed to copy backup snapshot to isolated restore target.');
}

$expectedChecksum = hash_file('sha256', $snapshotPath);
$actualChecksum = hash_file('sha256', $restoredPath);
if ($expectedChecksum !== $actualChecksum) {
  throw new RuntimeException('Restored snapshot checksum mismatch.');
}

$restoredPdo = new PDO('sqlite:' . $restoredPath);
$restoredPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$registerCollation($restoredPdo);

$integrity = (string) $restoredPdo->query('PRAGMA integrity_check')->fetchColumn();
if (strtolower(trim($integrity)) !== 'ok') {
  throw new RuntimeException("SQLite integrity_check failed: {$integrity}");
}

$counts = [];
foreach (['node_field_data', 'users_field_data', 'nusry_donations', 'nusry_newsletter_subscriptions', 'nusry_contact_submissions'] as $table) {
  $counts[$table] = (int) $restoredPdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
$restoredPdo = NULL;

if ($counts['node_field_data'] < 1 || $counts['users_field_data'] < 1 || $counts['nusry_donations'] < 1) {
  throw new RuntimeException('Restored database missing expected core or donation records.');
}

// Clean up temporary rehearsal artifacts; retain latest snapshot checksum summary.
@unlink($restoredPath);
@rmdir($restoreDir);

echo json_encode([
  'status' => 'ok',
  'snapshot' => '.local/backups/site-' . $stamp . '.sqlite',
  'sha256' => $expectedChecksum,
  'integrity_check' => $integrity,
  'row_counts' => $counts,
], JSON_PRETTY_PRINT) . "\n";
