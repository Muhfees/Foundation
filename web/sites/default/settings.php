<?php

/**
 * @file
 * Environment-based settings; local state is stored outside the public web root.
 */

$project_root = dirname($app_root);
$is_ddev = getenv('IS_DDEV_PROJECT') === 'true';
$is_sqlite = getenv('NUSRY_LOCAL_SQLITE') === '1';
$environment = getenv('NUSRY_ENV') ?: ($is_ddev ? 'local' : 'unconfigured');
$salt_file = $project_root . '/.local/hash-salt';
$settings['hash_salt'] = getenv('NUSRY_HASH_SALT') ?: '';
if (!$settings['hash_salt'] && in_array($environment, ['local', 'ci'], TRUE) && is_file($salt_file)) {
  $settings['hash_salt'] = trim(file_get_contents($salt_file));
}
if (!$settings['hash_salt']) {
  throw new \RuntimeException('Set NUSRY_HASH_SALT, or run the documented local setup.');
}
$settings['config_sync_directory'] = $project_root . '/config/sync';
$settings['file_private_path'] = $project_root . '/private';
$settings['trusted_host_patterns'] = getenv('NUSRY_TRUSTED_HOSTS')
  ? explode(',', getenv('NUSRY_TRUSTED_HOSTS'))
  : ['^localhost$', '^127\.0\.0\.1$', '^nusry-foundation\.ddev\.site$'];
$settings['update_free_access'] = FALSE;
$config['system.logging']['error_level'] = 'hide';

if ($is_sqlite && in_array($environment, ['local', 'ci'], TRUE)) {
  $databases['default']['default'] = [
    'driver' => 'sqlite',
    'database' => $project_root . '/.local/site.sqlite',
    'prefix' => '',
    'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
  ];
}
else {
  $databases['default']['default'] = [
    'driver' => 'mysql',
    'database' => getenv('NUSRY_DB_NAME') ?: ($is_ddev ? 'db' : ''),
    'username' => getenv('NUSRY_DB_USER') ?: ($is_ddev ? 'db' : ''),
    'password' => getenv('NUSRY_DB_PASSWORD') ?: ($is_ddev ? 'db' : ''),
    'host' => getenv('NUSRY_DB_HOST') ?: ($is_ddev ? 'db' : '127.0.0.1'),
    'port' => getenv('NUSRY_DB_PORT') ?: '3306',
    'prefix' => '',
    'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
    'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  ];
}

$redis_enabled = getenv('NUSRY_REDIS_ENABLED') === '1'
  || ($is_ddev && is_file($project_root . '/.local/redis-enabled'));
if ($redis_enabled) {
  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('NUSRY_REDIS_HOST') ?: 'redis';
  $settings['redis.connection']['port'] = 6379;
  $settings['cache']['default'] = 'cache.backend.redis';
}
