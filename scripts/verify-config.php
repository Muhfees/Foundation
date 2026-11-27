<?php

/**
 * @file
 * Fails CI if the active configuration differs from the committed export.
 */

$comparer = new \Drupal\Core\Config\StorageComparer(
  \Drupal::service('config.storage.sync'),
  \Drupal::service('config.storage'),
);
$comparer->createChangelist();
if ($comparer->hasChanges()) {
  throw new RuntimeException('Active configuration differs from config/sync.');
}
echo "Active configuration matches committed export.\n";
