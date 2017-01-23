<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\User;

// @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21Annotation%21FieldType.php/class/annotations/FieldType/8.2.x
create_field('field_user_email', 'email', 'user','user');
create_field('field_user_string', 'string', 'user','user');
create_field('field_user_string_long', 'string_long', 'user','user');
create_field('field_user_telephone', 'telephone', 'user','user');
create_field('field_user_text', 'text', 'user','user');
create_field('field_user_text_long', 'text_long', 'user','user');
create_field('field_user_text_with_summary', 'text_with_summary', 'user','user');

// @todo Find a Symfony-ish way to get arguments.
$args = drush_get_arguments();

// Create a user.
$values = [
  'field_user_email' => 'joe.user.alt@myhome.com',
  'field_user_string' => 'Private info',
  'field_user_string_long' => 'Really private info',
  'field_user_telephone' => '4104442222',
  'field_user_text' => 'Super private info',
  'field_user_text_long' => 'Super duper private info',
  'field_user_text_with_summary' => 'Private',
];

$user = User::create([
  'name' => $args[2],
  'mail' => $args[3],
  'pass' => 'password',
]);

foreach ($values as $field_name => $value) {
  $user->set($field_name, $value);
}

$return = $user->save();

/**
 * Create a field on an entity.
 *
 * @param string $field_name
 *   The name of the field.
 * @param string $field_type
 *   The field type.
 * @param string $entity_type
 *   The entity type. E.g., user.
 * @param $bundle
 *   The entity bundle. E.g., article.
 */
function create_field($field_name, $field_type, $entity_type, $bundle) {
  $field_storage = FieldStorageConfig::create(array(
    'field_name' => $field_name,
    'entity_type' => $entity_type,
    'type' => $field_type,
  ));
  $field_storage->save();
  FieldConfig::create([
    'field_storage' => $field_storage,
    'bundle' => $bundle,
    'label' => $field_name,
    'settings' => [],
  ])->save();
}
