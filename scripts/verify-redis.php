<?php

/**
 * @file
 * Proves Drupal is writing and reading through the Redis backend in CI.
 */

$cache = \Drupal::cache();
if (!$cache instanceof \Drupal\redis\Cache\CacheBase) {
  throw new RuntimeException('Default cache is not Redis.');
}
$key = 'nusry_ci_' . bin2hex(random_bytes(8));
$cache->set($key, 'verified', time() + 60);
if ($cache->get($key)->data !== 'verified') {
  throw new RuntimeException('Redis round-trip failed.');
}
$cache->delete($key);
echo "Redis backend round-trip passed.\n";
