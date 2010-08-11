<?php
// $Id$

// TODO: simulate
// TODO: take file from STDIN
// TODO: error handling
// TODO: review args/options
// TODO: info command. Use wizard for now.
// TODO: clone command
// TODO: field-delete

/**
 * Implementation of hook_drush_help().
 */
function field_drush_help($section) {
  switch ($section)  {
    case 'drush:field-create':
      return dt('Quickly create fields and instances for a given bundle.');
    case 'drush:field-update':
      return dt('Return URL for editing field.');
    case 'drush:field-clone':
      return dt('Clone a field and all of its instances.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function field_drush_command() {
  $items['field-create'] = array(
    'description' => 'Create fields and instances. Returns urls for field editing.',
    'core' => array(7),
    'drupal_dependencies' => array('field_ui'),
    'aliases' => array(),
    'arguments' => array(
      'bundle' => 'Content type (for nodes). Name of bundle to attach fields to. Required.',
      'field_spec' => 'Comma delimited triple in the form field_name,field_type,widget_name. Separate multiple fields by space. If omitted, a wizard will prompt you.'
    ),
    'options' => array(
      'entity_type' => 'Type of entity (e.g. node, user, comment). Defaults to node.',
    ),
    'examples' => array(
      'drush field-create article' => 'Define new article fields via interactive prompts.',
      'open `drush field-create article`' => 'Define new article fields and then open field edit form for refinement.',
      'drush field-create article city,text,text_textfield subtitle,text,text_textfield' => 'Create two new fields.'
    ),
  );
  $items['field-update'] = array(
    'description' => 'Return URL for field editing web page.',
    'core' => array(7),
    'drupal_dependencies' => array('field_ui'),
    'aliases' => array(),
    'arguments' => array(
      'field_name' => 'Name of field that needs updating.',
    ),
    'options' => array(
      'entity_type' => 'Type of entity (e.g. node, user, comment). Optional',
      'bundle' => 'Content type (for nodes). Name of bundle for specified field. Optional',
    ),
    'examples' => array(
      'field-update comment_body' => 'Quickly navigate to a field edit web page.',
    ),
  );
  $items['field-clone'] = array(
    'description' => 'Clone a field and all its instances.',
    'core' => array(7),
    'aliases' => array(),
    'arguments' => array(
      'source_field_name' => 'Name of field that will be cloned',
      'target_field_name' => 'Name of new, cloned field.',
    ),
    'examples' => array(
      'field-clone tags labels' => 'Copy \'tags\' field into a new field \'labels\' field which has same instances.',
      'open `field-clone tags labels`' => 'Clone field and then open field edit forms for refinement.',
    ),
  ); 
  return $items;
}

function drush_field_create($bundle) {
  $entity_type = drush_get_option('entity_type', 'node');
  
  $args = func_get_args();
  array_shift($args);
  if (empty($args)) {
    // Just one item in this array for now.
    $args[] = drush_field_create_wizard();
  }
  
  // Iterate over each field spec.
  foreach ($args as $string) {
    list($name, $type, $widget) = explode(',', $string);
    $info = field_info_field($field_name);
    if (empty($info)) {
      // Field does not exist already. Create it.
      $field = array(
        'field_name' => $name,
        'type' => $type,
      );
      field_create_field($field);
    }
    
    // Create the instance.
    $instance = array(
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'widget' => array('type' => $widget),
    );
    field_create_instance($instance);
    
    $urls[] = url(_field_ui_bundle_admin_path($entity_type, $bundle) . '/fields/' . $name, array('absolute' => TRUE));
  }
  drush_print(implode(' ', $urls));
}

function drush_field_update($field_name) {
   $info = field_info_field($field_name);
   if (!$entity_type = drush_get_option('entity_type')) {
     // For lack of better idea, use the first bundle in the list.
     $bundles = $info['bundles'];
     $entity_type = key($bundles);
     $bundle = current($bundles[$entity_type]);
   }
  $urls[] = url(_field_ui_bundle_admin_path($entity_type, $bundle) . '/fields/' . $field_name, array('absolute' => TRUE));
  drush_print(implode(' ', $urls));
}

function drush_field_clone($source_field_name, $target_field_name) {
   if (!$info = field_info_field($source_field_name)) {
     return drush_set_error(dt('%source not found in field list.', array('%source' => $source_field_name)));
   }
   
   unset($info['id']);
   $info['field_name'] = $target_field_name;
   $target = field_create_field($info);
   
   foreach ($info['bundles'] as $entity_type => $bundles) {
     foreach ($bundles as $bundle) {
       $instance = field_info_instance($entity_type, $source_field_name, $bundle);
       $instance['field_name'] = $target_field_name;
       unset($instance['id']);
       $instance['field_id'] = $target['id'];
       field_create_instance($instance);
       $urls[] = url(_field_ui_bundle_admin_path($entity_type, $bundle) . '/fields/' . $target_field_name, array('absolute' => TRUE));
     }
   }
   
  drush_print(implode(' ', $urls));
}

/**
 * Prompt user enough to create basic field and instance.
 *
 * @return array $field_spec
 *   An array of brief field specifications.
 */
function drush_field_create_wizard() {
  $specs[] = drush_prompt(dt('Field name'));
  module_load_include('inc', 'field_ui', 'field_ui.admin');
  $types = field_ui_field_type_options();
  $field_type = drush_choice($types, dt('Choose a field type'));
  $specs[] = $field_type;
  $widgets = field_ui_widget_type_options($field_type);
  $specs[] = drush_choice($widgets, dt('Choose a widget'));
  return implode(',', $specs);
}