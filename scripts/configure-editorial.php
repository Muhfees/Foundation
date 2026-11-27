<?php

/**
 * @file
 * Development-only model builder; exported config is the deployment source.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;

if (!in_array(getenv('NUSRY_ENV'), ['local', 'ci'], TRUE)) {
  throw new RuntimeException('Only local/CI model building is supported.');
}

$field = static function (string $entity, string $bundle, string $name, string $label, string $type, array $options = []): void {
  if (!FieldStorageConfig::loadByName($entity, $name)) {
    FieldStorageConfig::create([
      'entity_type' => $entity, 'field_name' => $name, 'type' => $type,
      'cardinality' => $options['cardinality'] ?? 1,
      'settings' => $options['storage'] ?? [],
    ])->save();
  }
  if (!FieldConfig::loadByName($entity, $bundle, $name)) {
    FieldConfig::create([
      'entity_type' => $entity, 'bundle' => $bundle, 'field_name' => $name,
      'label' => $label, 'required' => $options['required'] ?? FALSE,
      'description' => $options['description'] ?? '',
      'settings' => $options['settings'] ?? [],
      'default_value' => $options['default'] ?? [],
      'translatable' => TRUE,
    ])->save();
  }
  $definition = \Drupal::service('plugin.manager.field.field_type')->getDefinition($type);
  $id = "$entity.$bundle.default";
  $form = EntityFormDisplay::load($id) ?: EntityFormDisplay::create([
    'targetEntityType' => $entity, 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE,
  ]);
  $form->setComponent($name, [
    'type' => $options['widget'] ?? $definition['default_widget'],
    'weight' => count($form->getComponents()),
    'settings' => $options['widget_settings'] ?? [],
  ])->save();
  $view = EntityViewDisplay::load($id) ?: EntityViewDisplay::create([
    'targetEntityType' => $entity, 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE,
  ]);
  if ($options['hidden'] ?? FALSE) {
    $view->removeComponent($name)->save();
  }
  else {
    $view->setComponent($name, [
      'type' => $options['formatter'] ?? $definition['default_formatter'],
      'label' => 'above', 'weight' => count($view->getComponents()),
      'settings' => $options['formatter_settings'] ?? [],
    ])->save();
  }
};

foreach (['image' => 'Image', 'document' => 'Document', 'remote_video' => 'Remote video'] as $bundle => $label) {
  $source = ['image' => 'image', 'document' => 'file', 'remote_video' => 'oembed:video'][$bundle];
  $name = 'field_media_' . $bundle;
  if (!MediaType::load($bundle)) {
    MediaType::create(['id' => $bundle, 'label' => $label, 'source' => $source,
      'source_configuration' => ['source_field' => $name], 'new_revision' => TRUE,
      'queue_thumbnail_downloads' => TRUE, 'status' => FALSE,
    ])->save();
  }
  $options = ['required' => TRUE];
  if ($bundle !== 'remote_video') {
    $options['storage'] = ['uri_scheme' => 'private'];
    $options['settings'] = ['file_directory' => 'editorial/[date:custom:Y-m]', 'file_extensions' => $bundle === 'image' ? 'png jpg jpeg webp' : 'pdf txt', 'max_filesize' => '10 MB'];
  }
  if ($bundle === 'image') {
    $options['settings'] += ['alt_field' => TRUE, 'alt_field_required' => FALSE];
    $options['widget'] = 'image_focal_point';
  }
  $field('media', $bundle, $name, $label . ' file or URL', ['image' => 'image', 'document' => 'file', 'remote_video' => 'string'][$bundle], $options);
  if ($bundle === 'image') {
    $existing_image_field = FieldConfig::loadByName('media', 'image', 'field_media_image');
    if ($existing_image_field && $existing_image_field->getSetting('alt_field_required')) {
      $existing_image_field->setSetting('alt_field_required', FALSE)->save();
    }
    $field('media', 'image', 'field_decorative', 'Decorative image (alt text omitted)', 'boolean', ['default' => [['value' => 0]]]);
    $field('media', 'image', 'field_sensitive', 'Contains sensitive imagery', 'boolean', ['default' => [['value' => 0]]]);
    $field('media', 'image', 'field_caption', 'Caption', 'string_long', []);
  }
  foreach (['field_credit' => 'Credit', 'field_license' => 'License / permission', 'field_provenance' => 'Source and provenance notes'] as $key => $title) {
    $field('media', $bundle, $key, $title, 'string_long', ['required' => TRUE]);
  }
  $field('media', $bundle, 'field_source_url', 'Source URL', 'link', ['required' => TRUE, 'settings' => ['link_type' => 16, 'title' => 0]]);
  if ($bundle !== 'image') {
    $field('media', $bundle, 'field_accessible_text', $bundle === 'document' ? 'Accessible text equivalent' : 'Transcript', 'string_long', ['required' => TRUE]);
  }
  if ($bundle === 'remote_video') {
    $field('media', 'remote_video', 'field_caption_status', 'Captions verified', 'boolean', ['default' => [['value' => 1]]]);
  }
}
echo "Media bundles and editorial fields configured.\n";

$model = json_decode(file_get_contents(__DIR__ . '/editorial-model.json'), TRUE, 512, JSON_THROW_ON_ERROR);
foreach (['regions', 'story_categories', 'event_formats', 'campaign_themes', 'report_categories', 'giving_methods'] as $vocabulary) {
  if (!\Drupal\taxonomy\Entity\Vocabulary::load($vocabulary)) {
    \Drupal\taxonomy\Entity\Vocabulary::create(['vid' => $vocabulary, 'name' => ucwords(str_replace('_', ' ', $vocabulary))])->save();
  }
}
if (!\Drupal\filter\Entity\FilterFormat::load('restricted_html')) {
  \Drupal\filter\Entity\FilterFormat::create([
    'format' => 'restricted_html', 'name' => 'Restricted editorial HTML', 'weight' => 0,
    'filters' => [
      'filter_html' => ['status' => TRUE, 'settings' => ['allowed_html' => '<p> <br> <strong> <em> <ul> <ol start> <li> <h2> <h3> <blockquote> <a href title>', 'filter_html_help' => TRUE, 'filter_html_nofollow' => FALSE]],
      'filter_htmlcorrector' => ['status' => TRUE],
    ],
  ])->save();
  \Drupal\editor\Entity\Editor::create([
    'format' => 'restricted_html', 'editor' => 'ckeditor5',
    'settings' => ['toolbar' => ['items' => ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']], 'plugins' => ['ckeditor5_heading' => ['enabled_headings' => ['heading2', 'heading3']]]],
    'image_upload' => ['status' => FALSE],
  ])->save();
}
foreach ($model['paragraphs'] as $bundle => $fields) {
  if (!\Drupal\paragraphs\Entity\ParagraphsType::load($bundle)) {
    \Drupal\paragraphs\Entity\ParagraphsType::create(['id' => $bundle, 'label' => ucwords(str_replace('_', ' ', $bundle))])->save();
  }
}
foreach ($model['nodes'] as $bundle => $spec) {
  if (!\Drupal\node\Entity\NodeType::load($bundle)) {
    \Drupal\node\Entity\NodeType::create(['type' => $bundle, 'name' => $spec['label'], 'new_revision' => TRUE, 'display_submitted' => FALSE])->save();
  }
}
foreach ($model['paragraphs'] as $bundle => $fields) {
  foreach ($fields as $spec) {
    $field('paragraph', $bundle, $spec['name'], $spec['label'], $spec['type'], $spec['options']);
  }
}
$top_level = array_filter(array_keys($model['paragraphs']), static fn($key) => !str_ends_with($key, '_item'));
foreach ($model['nodes'] as $bundle => $spec) {
  $field('node', $bundle, 'field_summary', 'Summary', 'string_long', ['required' => TRUE]);
  $field('node', $bundle, 'field_demo', 'Fictional demo content', 'boolean', ['default' => [['value' => 1]], 'hidden' => TRUE]);
  $field('node', $bundle, 'field_metatags', 'SEO metadata', 'metatag', ['hidden' => TRUE]);
  $field('node', $bundle, 'field_social_image', 'Social sharing image', 'entity_reference', ['storage' => ['target_type' => 'media'], 'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => ['image' => 'image']]], 'widget' => 'media_library_widget', 'hidden' => TRUE]);
  $field('node', $bundle, 'field_components', 'Page components', 'entity_reference_revisions', [
    'storage' => ['target_type' => 'paragraph'], 'cardinality' => -1,
    'settings' => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => array_combine($top_level, $top_level)]],
    'widget' => 'paragraphs', 'formatter' => 'entity_reference_revisions_entity_view',
  ]);
  if (in_array($bundle, ['story', 'country', 'medical_issue', 'campaign'], TRUE)) {
    $field('node', $bundle, 'field_hero', 'Hero image', 'entity_reference', ['required' => TRUE, 'storage' => ['target_type' => 'media'], 'settings' => ['handler' => 'default:media', 'handler_settings' => ['target_bundles' => ['image' => 'image']]], 'widget' => 'media_library_widget', 'formatter' => 'entity_reference_entity_view']);
  }
  foreach ($spec['fields'] as $definition) {
    $field('node', $bundle, $definition['name'], $definition['label'], $definition['type'], $definition['options']);
  }
  if (!\Drupal\pathauto\Entity\PathautoPattern::load($bundle)) {
    \Drupal\pathauto\Entity\PathautoPattern::create([
      'id' => $bundle, 'label' => $spec['label'], 'type' => 'canonical_entities:node', 'pattern' => $spec['path'],
      'selection_criteria' => ['type' => ['id' => 'entity_bundle:node', 'bundles' => [$bundle => $bundle], 'negate' => FALSE, 'context_mapping' => ['node' => 'node']]],
      'selection_logic' => 'and', 'weight' => 0,
    ])->save();
  }
}
echo "Seven node types and constrained Paragraphs library configured.\n";

foreach (array_keys($model['nodes']) as $bundle) {
  $form = EntityFormDisplay::load("node.$bundle.default");
  $form->setComponent('title', ['type' => 'string_textfield', 'weight' => -20]);
  $form->setComponent('path', ['type' => 'path', 'weight' => 80]);
  $form->setComponent('moderation_state', ['type' => 'moderation_state_default', 'weight' => 90]);
  $form->setComponent('revision_log', ['type' => 'string_textarea', 'weight' => 95]);
  $form->save();
}
foreach (['image', 'document', 'remote_video'] as $bundle) {
  EntityFormDisplay::load("media.$bundle.default")
    ->setComponent('name', ['type' => 'string_textfield', 'weight' => -20])
    ->setComponent('moderation_state', ['type' => 'moderation_state_default', 'weight' => 90])
    ->save();
}

$workflow = \Drupal\workflows\Entity\Workflow::load('editorial') ?: \Drupal\workflows\Entity\Workflow::create(['id' => 'editorial', 'label' => 'Editorial review', 'type' => 'content_moderation']);
$states = [];
foreach (['draft' => 'Draft', 'review' => 'Needs review', 'scheduled' => 'Approved for scheduling', 'published' => 'Published', 'archived' => 'Archived'] as $key => $label) {
  $states[$key] = ['label' => $label, 'weight' => count($states), 'published' => $key === 'published', 'default_revision' => in_array($key, ['published', 'archived'], TRUE)];
}
$transitions = [
  'create_new_draft' => ['label' => 'Save draft', 'from' => array_keys($states), 'to' => 'draft', 'weight' => 0],
  'submit_review' => ['label' => 'Submit for review', 'from' => ['draft', 'review'], 'to' => 'review', 'weight' => 1],
  'approve_schedule' => ['label' => 'Approve for scheduling', 'from' => ['review', 'scheduled'], 'to' => 'scheduled', 'weight' => 2],
  'publish' => ['label' => 'Publish approved content', 'from' => ['review', 'scheduled', 'published'], 'to' => 'published', 'weight' => 3],
  'archive' => ['label' => 'Archive', 'from' => ['published', 'archived'], 'to' => 'archived', 'weight' => 4],
];
$workflow->set('type_settings', ['states' => $states, 'transitions' => $transitions, 'entity_types' => ['node' => array_keys($model['nodes']), 'media' => ['image', 'document', 'remote_video']], 'default_moderation_state' => 'draft'])->save();
foreach (array_keys($model['nodes']) as $bundle) {
  $type = \Drupal\node\Entity\NodeType::load($bundle);
  foreach (['publish_enable' => TRUE, 'unpublish_enable' => TRUE, 'publish_revision' => TRUE, 'unpublish_revision' => TRUE, 'publish_past_date' => 'error', 'publish_touch' => FALSE] as $key => $value) {
    $type->setThirdPartySetting('scheduler', $key, $value);
  }
  $type->save();
  $form = EntityFormDisplay::load("node.$bundle.default");
  foreach (['publish_on', 'unpublish_on'] as $name) {
    $form->setComponent($name, ['type' => 'datetime_timestamp_no_default', 'weight' => 60]);
  }
  foreach (['publish_state', 'unpublish_state'] as $name) {
    $form->setComponent($name, ['type' => 'scheduler_moderation', 'weight' => 61]);
  }
  $form->save();
}
$basic = ['access content', 'access content overview', 'access administration pages', 'view the administration theme', 'view own unpublished content', 'view latest version', 'use text format restricted_html', 'view media', 'view own unpublished media', 'access media overview'];
$roles = ['administrator' => [], 'editor' => $basic, 'contributor' => $basic, 'donor_services' => ['access content'], 'demo_donor' => ['access content']];
foreach (array_keys($model['nodes']) as $bundle) {
  $roles['contributor'] = array_merge($roles['contributor'], ["create $bundle content", "edit own $bundle content"]);
  $roles['editor'] = array_merge($roles['editor'], ["create $bundle content", "edit any $bundle content", "delete any $bundle content", "view $bundle revisions", "revert $bundle revisions"]);
}
foreach (['image', 'document', 'remote_video'] as $bundle) {
  $roles['contributor'] = array_merge($roles['contributor'], ["create $bundle media", "edit own $bundle media"]);
  $roles['editor'] = array_merge($roles['editor'], ["create $bundle media", "edit any $bundle media", "delete any $bundle media"]);
}
foreach (array_keys($transitions) as $transition) {
  $roles['editor'][] = "use editorial transition $transition";
}
$roles['contributor'][] = 'use editorial transition create_new_draft';
$roles['contributor'][] = 'use editorial transition submit_review';
$roles['editor'] = array_merge($roles['editor'], ['view any unpublished content', 'view all media revisions', 'schedule publishing of nodes', 'view scheduled content', 'create url aliases']);
foreach ($roles as $id => $permissions) {
  $role = \Drupal\user\Entity\Role::load($id) ?: \Drupal\user\Entity\Role::create(['id' => $id, 'label' => ucwords(str_replace('_', ' ', $id))]);
  $role->set('is_admin', $id === 'administrator')->set('permissions', array_values(array_unique($permissions)))->save();
}
\Drupal\user\Entity\Role::load('anonymous')->grantPermission('view media')->save();
\Drupal\user\Entity\Role::load('authenticated')->grantPermission('view media')->save();
\Drupal::configFactory()->getEditable('node.settings')->set('use_admin_theme', TRUE)->save();
\Drupal::configFactory()->getEditable('system.date')->set('timezone.default', 'UTC')->save();
if (!\Drupal\system\Entity\Menu::load('donate')) {
  \Drupal\system\Entity\Menu::create(['id' => 'donate', 'label' => 'Donate menu', 'description' => 'Giving options and demo donation entry points.'])->save();
}
foreach (['hero_desktop' => [1440, 720], 'hero_mobile' => [750, 900], 'card_landscape' => [720, 480]] as $style_id => [$width, $height]) {
  if (!\Drupal\image\Entity\ImageStyle::load($style_id)) {
    $style = \Drupal\image\Entity\ImageStyle::create(['name' => $style_id, 'label' => ucwords(str_replace('_', ' ', $style_id)) . " ({$width}x{$height})"]);
    $style->addImageEffect(['id' => 'focal_point_scale_and_crop', 'weight' => 1, 'data' => ['width' => $width, 'height' => $height, 'crop_type' => 'focal_point']]);
    $style->save();
  }
}
foreach (array_keys($model['nodes']) as $bundle) {
  $settings = \Drupal\language\Entity\ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);
  $settings->setLanguageAlterable(TRUE)->setThirdPartySetting('content_translation', 'enabled', TRUE)->save();
}
foreach (['image', 'document', 'remote_video'] as $bundle) {
  $settings = \Drupal\language\Entity\ContentLanguageSettings::loadByEntityTypeBundle('media', $bundle);
  $settings->setLanguageAlterable(TRUE)->setThirdPartySetting('content_translation', 'enabled', TRUE)->save();
}
\Drupal::configFactory()->getEditable('metatag.metatag_defaults.node')->set('tags', [
  'title' => '[node:title] | [site:name]',
  'description' => '[node:summary]',
  'canonical_url' => '[node:url]',
  'og_title' => '[node:title]',
  'og_description' => '[node:summary]',
  'og_image' => '[node:field_social_image:entity:field_media_image:entity:url]',
])->save();
$nusry_config = \Drupal::configFactory()->getEditable('nusry_core.settings');
if ($nusry_config->isNew()) {
  $defaults = \Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__) . '/web/modules/custom/nusry_core/config/install/nusry_core.settings.yml');
  $nusry_config->setData($defaults)->save();
}
echo "Review workflow, scheduling, menus, focal-point image styles, translation readiness and least-privilege roles configured.\n";
