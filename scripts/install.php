<?php

/**
 * @file
 * Initializes a fresh local/CI database without reinstalling an existing site.
 */

use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
$environment = getenv('NUSRY_ENV');
if (!in_array($environment, ['local', 'ci'], TRUE)) {
  throw new RuntimeException('Installer is restricted to explicit local or CI environments.');
}
foreach (['.local', 'private', 'web/sites/default/files'] as $directory) {
  if (!is_dir($root . '/' . $directory)) {
    mkdir($root . '/' . $directory, 0700, TRUE);
  }
}
if (!is_file($root . '/.local/hash-salt')) {
  file_put_contents($root . '/.local/hash-salt', bin2hex(random_bytes(32)), LOCK_EX);
  chmod($root . '/.local/hash-salt', 0600);
}
$app_root = $root . '/web';
require $app_root . '/sites/default/settings.php';
$db = $databases['default']['default'];
if ($db['driver'] === 'sqlite') {
  $pdo = new PDO('sqlite:' . $db['database']);
  $table_count = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
}
else {
  $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['database']}", $db['username'], $db['password']);
  $table_count = count($pdo->query('SHOW TABLES')->fetchAll());
}
$password = bin2hex(random_bytes(24));
$drush = static function (array $arguments) use ($root, $password): string {
  $process = new Process([PHP_BINARY, $root . '/vendor/drush/drush/drush.php', '--root=' . $root . '/web', ...$arguments], $root);
  $process->setTimeout(300);
  $process->run();
  $output = $process->getOutput() . $process->getErrorOutput();
  if (!$process->isSuccessful()) {
    throw new RuntimeException(str_replace($password, '[redacted]', $output));
  }
  return trim($output);
};
if ($table_count > 0) {
  $status = $drush(['status', '--field=bootstrap']);
  if (!str_contains($status, 'Successful')) {
    throw new RuntimeException('Database is nonempty but Drupal cannot bootstrap. Refusing to overwrite it.');
  }
  echo "Existing Drupal site preserved; no reinstall or configuration import performed.\n";
  exit(0);
}
$existing_config = is_file($root . '/config/sync/core.extension.yml');
$arguments = ['site:install', '--yes', '--site-name=Nusry Foundation', '--account-name=demo-admin', '--account-pass=' . $password];
$arguments[] = $existing_config ? '--existing-config' : 'minimal';
$drush($arguments);
if (!str_contains($drush(['status', '--field=bootstrap']), 'Successful')) {
  throw new RuntimeException('Installation did not produce a bootable Drupal site.');
}
file_put_contents($root . '/.local/admin-password', $password, LOCK_EX);
chmod($root . '/.local/admin-password', 0600);
if (!$existing_config) {
  $drush(['pm:enable', 'block', 'mysql', 'sqlite', 'redis', 'nusry_core', '--yes']);
  $drush(['theme:enable', 'nusry', '--yes']);
  $drush(['config:set', 'system.theme', 'default', 'nusry', '--yes']);
  $drush(['config:set', 'system.site', 'page.front', '/welcome', '--yes']);
  $drush(['role:perm:add', 'anonymous', 'access content']);
  $drush(['config:export', '--yes']);
}
$drush(['cache:rebuild']);
echo "Drupal installed. Local administrator: demo-admin. Generated password is in .local/admin-password (never commit it).\n";
