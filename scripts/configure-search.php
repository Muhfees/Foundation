<?php

/**
 * @file
 * Configures Search API database server, editorial index, breadcrumbs and 404.
 */

use Drupal\block\Entity\Block;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;

if (!in_array(getenv('NUSRY_ENV'), ['local', 'ci'], TRUE)) {
  throw new RuntimeException('Only local/CI search configuration is supported.');
}

if (!Server::load('database')) {
  Server::create([
    'id' => 'database',
    'name' => 'Database Search Server',
    'description' => 'Local Search API database backend.',
    'backend' => 'search_api_db',
    'backend_config' => [
      'database' => 'default:default',
      'min_chars' => 2,
      'matching' => 'partial',
    ],
    'status' => TRUE,
  ])->save();
}

$fieldsHelper = \Drupal::service('search_api.fields_helper');
$processorPluginManager = \Drupal::service('plugin.manager.search_api.processor');

$index = Index::load('editorial_content');
if (!$index) {
  $index = Index::create([
    'id' => 'editorial_content',
    'name' => 'Editorial content',
    'description' => 'Indexes published stories, countries, medical issues, events, campaigns, reports and flexible pages.',
    'read_only' => FALSE,
    'server' => 'database',
    'status' => TRUE,
    'options' => [
      'cron_limit' => 50,
      'index_directly' => TRUE,
    ],
    'datasource_settings' => [
      'entity:node' => [],
    ],
    'tracker_settings' => [
      'default' => [],
    ],
  ]);

  $fieldSpecs = [
    'title' => ['property' => 'title', 'type' => 'text', 'boost' => 5.0, 'label' => 'Title'],
    'type' => ['property' => 'type', 'type' => 'string', 'label' => 'Content type'],
    'created' => ['property' => 'created', 'type' => 'date', 'label' => 'Authored on'],
    'status' => ['property' => 'status', 'type' => 'boolean', 'label' => 'Published'],
    'uid' => ['property' => 'uid', 'type' => 'integer', 'label' => 'Author uid'],
    'field_summary' => ['property' => 'field_summary', 'type' => 'text', 'boost' => 2.0, 'label' => 'Summary'],
    'field_body' => ['property' => 'field_body', 'type' => 'text', 'boost' => 1.0, 'label' => 'Body'],
    'field_story_kind' => ['property' => 'field_story_kind', 'type' => 'string', 'label' => 'Story type'],
    'field_date' => ['property' => 'field_date', 'type' => 'date', 'label' => 'Publication date'],
    'field_countries' => ['property' => 'field_countries', 'type' => 'integer', 'label' => 'Countries'],
    'field_issues' => ['property' => 'field_issues', 'type' => 'integer', 'label' => 'Medical issues'],
  ];

  foreach ($fieldSpecs as $fieldId => $spec) {
    $field = $fieldsHelper->createField($index, $fieldId, [
      'datasource_id' => 'entity:node',
      'property_path' => $spec['property'],
      'type' => $spec['type'],
      'label' => $spec['label'],
    ]);
    if (isset($spec['boost'])) {
      $field->setBoost($spec['boost']);
    }
    $index->addField($field);
  }

  foreach (['entity_status', 'content_access', 'html_filter', 'ignorecase', 'tokenizer'] as $processorId) {
    if ($processorPluginManager->hasDefinition($processorId)) {
      $processor = $processorPluginManager->createInstance($processorId, ['#index' => $index]);
      $index->addProcessor($processor);
    }
  }

  $index->save();
}

$indexed = $index->indexItems(200);

if (!Block::load('nusry_breadcrumbs')) {
  Block::create([
    'id' => 'nusry_breadcrumbs',
    'theme' => 'nusry',
    'region' => 'breadcrumb',
    'weight' => 0,
    'plugin' => 'system_breadcrumb_block',
    'settings' => [
      'id' => 'system_breadcrumb_block',
      'label' => 'Breadcrumbs',
      'label_display' => '0',
      'provider' => 'system',
    ],
    'visibility' => [
      'request_path' => [
        'id' => 'request_path',
        'negate' => TRUE,
        'pages' => '<front>',
      ],
    ],
  ])->save();
}

\Drupal::configFactory()->getEditable('system.site')->set('page.404', '/not-found')->save();

echo "Search API configured (indexed $indexed items), breadcrumbs block added, and 404 route configured.\n";
