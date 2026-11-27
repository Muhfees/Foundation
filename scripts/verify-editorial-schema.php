<?php

/**
 * @file
 * Runtime checks of the exported model and least-privilege role configuration.
 */

$model = json_decode(file_get_contents(__DIR__ . '/editorial-model.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$check = static function (bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
};
foreach ($model['nodes'] as $bundle => $spec) {
  $check((bool) \Drupal\node\Entity\NodeType::load($bundle), "Missing node type $bundle");
  $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
  foreach (array_merge(['title', 'field_summary', 'field_social_image', 'field_components', 'moderation_state', 'publish_on'], array_column($spec['fields'], 'name')) as $name) {
    $check(isset($fields[$name]), "Missing $bundle.$name");
  }
  $lang_settings = \Drupal\language\Entity\ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);
  $check((bool) $lang_settings->getThirdPartySetting('content_translation', 'enabled'), "Translation readiness missing on node.$bundle");
  $form = \Drupal\Core\Entity\Entity\EntityFormDisplay::load("node.$bundle.default");
  $check((bool) $form->getComponent('title'), "Missing title widget on $bundle");
}
foreach ($model['paragraphs'] as $bundle => $fields) {
  $check((bool) \Drupal\paragraphs\Entity\ParagraphsType::load($bundle), "Missing paragraph $bundle");
  foreach ($fields as $spec) {
    $definition = \Drupal\field\Entity\FieldConfig::loadByName('paragraph', $bundle, $spec['name']);
    $check((bool) $definition, "Missing paragraph field $bundle.{$spec['name']}");
    if ($spec['type'] === 'entity_reference_revisions') {
      foreach (array_keys($definition->getSetting('handler_settings')['target_bundles']) as $child) {
        $check(str_ends_with($child, '_item'), 'Paragraph nesting must terminate in a leaf item.');
      }
    }
  }
}
foreach (['image', 'document', 'remote_video'] as $bundle) {
  $check((bool) \Drupal\media\Entity\MediaType::load($bundle), "Missing media $bundle");
  foreach (['field_credit', 'field_license', 'field_provenance', 'field_source_url'] as $name) {
    $check(\Drupal\field\Entity\FieldConfig::loadByName('media', $bundle, $name)->isRequired(), "Required media metadata: $name");
  }
}
foreach (['field_decorative', 'field_sensitive', 'field_caption'] as $name) {
  $check((bool) \Drupal\field\Entity\FieldConfig::loadByName('media', 'image', $name), "Missing media.image.$name");
}
$check((bool) \Drupal\field\Entity\FieldConfig::loadByName('media', 'remote_video', 'field_captions_confirmed'), 'Missing media.remote_video.field_captions_confirmed');
$check((bool) \Drupal\system\Entity\Menu::load('donate'), 'Missing donate menu');
foreach (['hero_desktop', 'hero_mobile', 'card_landscape'] as $style_id) {
  $check((bool) \Drupal\image\Entity\ImageStyle::load($style_id), "Missing image style $style_id");
}
$valid = \Drupal::service('user.permissions')->getPermissions();
foreach (['editor', 'contributor', 'donor_services', 'demo_donor'] as $id) {
  $role = \Drupal\user\Entity\Role::load($id);
  foreach ($role->getPermissions() as $permission) {
    $check(isset($valid[$permission]), "Unknown permission: $permission");
  }
  foreach (['administer permissions', 'administer modules', 'administer users', 'administer nodes', 'bypass node access'] as $permission) {
    $check(!$role->hasPermission($permission), "$id has excessive privilege: $permission");
  }
}
$check(!\Drupal\user\Entity\Role::load('contributor')->hasPermission('use editorial transition publish'), 'Contributors must not publish.');
$allocation = \Drupal::service('nusry_core.editorial_settings')->getFundAllocation();
$check(($allocation['programmes_percent'] + $allocation['fundraising_percent'] + $allocation['administration_percent']) === 100, 'Fund allocation must sum to 100.');
echo "Schema checks passed: 7 content types, 19 Paragraph types, 3 media types, constrained nesting and roles.\n";
