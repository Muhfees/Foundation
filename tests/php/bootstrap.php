<?php

/**
 * @file
 * Autoloader bootstrap for Unit and Kernel PHPUnit tests without Mink.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$webRoot = $root . '/web';
$loader = require $root . '/vendor/autoload.php';

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
  define('PHPUNIT_COMPOSER_INSTALL', $root . '/vendor/autoload.php');
}

$loader->add('Drupal\\Tests', $webRoot . '/core/tests');
$loader->add('Drupal\\KernelTests', $webRoot . '/core/tests');
$loader->add('Drupal\\TestTools', $webRoot . '/core/tests');

$scanRoots = array_filter([
  $webRoot . '/core/modules',
  $webRoot . '/core/profiles',
  $webRoot . '/core/themes',
  $webRoot . '/modules',
  $webRoot . '/profiles',
  $webRoot . '/themes',
]);

foreach ($scanRoots as $scanRoot) {
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::FOLLOW_SYMLINKS));
  foreach ($iterator as $file) {
    if (str_contains($file->getPathname(), '.info.yml')) {
      $extension = substr($file->getFilename(), 0, -9);
      $dir = $file->getPathInfo()->getRealPath();
      if (is_dir($dir . '/src')) {
        $loader->addPsr4('Drupal\\' . $extension . '\\', [$dir . '/src']);
      }
      if (is_dir($dir . '/tests/src')) {
        $loader->addPsr4('Drupal\\Tests\\' . $extension . '\\', [$dir . '/tests/src']);
      }
    }
  }
}

setlocale(LC_ALL, 'C.UTF-8', 'C');
mb_internal_encoding('utf-8');
mb_language('uni');
date_default_timezone_set('UTC');
