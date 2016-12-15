<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\Entity\User;

// @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21Annotation%21FieldWidget.php/class/annotations/FieldWidget/8.2.x
// @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21Annotation%21FieldType.php/class/annotations/FieldType/8.2.x
create_field('field_user_email', 'email', 'email_default', 'user',' user');
create_field('field_user_string', 'string', 'string_textfield', 'user',' user');
create_field('field_user_string_long', 'string_long', 'string_textarea', 'user',' user');
create_field('field_user_telephone', 'telephone', 'telephone_default', 'user',' user');
create_field('field_user_text', 'text', 'text_textfield', 'user',' user');
create_field('field_user_text_long', 'text_long', 'text_textarea', 'user',' user');
create_field('field_user_text_with_summary', 'text_with_summary', 'text_textarea_with_summary', 'user',' user');

// @todo Find a Symfony-ish way to get arguments.
$args = drush_get_arguments();

// Create a user.
$values = [
  'name' => $args[2],
  'mail' => $args[3],
  'password' => 'password',
  'field_user_email' => 'joe.user.alt@myhome.com',
  'field_user_string' => 'Private info',
  'field_user_string_long' => 'Really private info',
  'field_user_telephone' => '5555555555',
  'field_user_text' => 'Super private info',
  'field_user_text_long' => 'Super duper private info',
  'field_user_text_with_summary' => 'Private',
];
$user = User::create($values);
$return = $user->save();

/**
 * Create a field on an entity.
 *
 * @param string $field_name
 *   The name of the field.
 * @param string $field_type
 *   The field type.
 * @param string $widget_type
 *   The widget type.
 * @param string $entity_type
 *   The entity type. E.g., user.
 * @param $bundle
 *   The entity bundle. E.g., article.
 */
function create_field($field_name, $field_type, $widget_type, $entity_type, $bundle) {
  FieldStorageConfig::create(array(
    'field_name' => $field_name,
    'entity_type' => $entity_type,
    'type' => $field_type,
  ))->save();
  FieldConfig::create([
    'entity_type' => $entity_type,
    'field_name' => $field_name,
    'bundle' => $bundle,
  ])->save();

  // Create a form display for the default form mode.
  entity_get_form_display($entity_type, $bundle, 'default')
    ->setComponent($field_name, array(
      'type' => $widget_type,
    ))
    ->save();
}
