<?php

/**
 * @file
 *  Field API's drush integration
 */

/**
 * Implementation of hook_drush_help().
 */
function field_drush_help($section) {
  switch ($section) {
    case 'meta:field:title':
      return dt('Field commands');
    case 'meta:field:summary':
      return dt('Manipulate Drupal 7+ fields.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function field_drush_command() {
  $items['field-create'] = array(
    'description' => 'Create fields and instances. Returns urls for field editing.',
    'core' => array('7+'),
    'drupal_dependencies' => array('field_ui'),
    'arguments' => array(
      'bundle' => 'Content type (for nodes). Name of bundle to attach fields to. Required.',
      'field_spec' => 'Comma delimited triple in the form: field_name,field_type,widget_name. If widget_name is omitted, the default widget will be used. Separate multiple fields by space. If omitted, a wizard will prompt you.'
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
    'core' => array('7+'),
    'drupal_dependencies' => array('field_ui'),
    'arguments' => array(
      'field_name' => 'Name of field that needs updating.',
    ),
    'examples' => array(
      'field-update comment_body' => 'Quickly navigate to a field edit web page.',
    ),
  );
  $items['field-delete'] = array(
    'description' => 'Delete a field and its instances.',
    'core' => array('7+'),
    'arguments' => array(
      'field_name' => 'Name of field to delete.',
    ),
    'options' => array(
      'bundle' => 'Only delete the instance attached to this bundle. If omitted, admin can choose to delete one instance or whole field.',
      'entity_type' => 'Disambiguate a particular bundle from identically named bundles. Usually not needed.'
    ),
    'examples' => array(
      'field-delete city' => 'Delete the city field and any instances it might have.',
      'field-delete city --bundle=article' => 'Delete the city instance on the article bundle',
    ),
  );
  $items['field-clone'] = array(
    'description' => 'Clone a field and all its instances.',
    'core' => array('7+'),
    'arguments' => array(
      'source_field_name' => 'Name of field that will be cloned',
      'target_field_name' => 'Name of new, cloned field.',
    ),
    'examples' => array(
      'field-clone tags labels' => 'Copy \'tags\' field into a new field \'labels\' field which has same instances.',
      'open `field-clone tags labels`' => 'Clone field and then open field edit forms for refinement.',
    ),
  );
  $items['field-info'] = array(
    'description' => 'View information about fields, field_types, and widgets.',
    'drupal_dependencies' => array('field_ui'),
    'core' => array('7+'),
    'arguments' => array(
      'type' => 'Recognized values: fields, types. If omitted, a choice list appears.',
    ),
    'options' => array(
      'pipe' => 'Return field information table as CSV.',
    ),
    'examples' => array(
      'field-info types' => 'Show a table which lists all field types and their available widgets',
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
    $info = field_info_field($name);
    if (empty($info)) {
      // Field does not exist already. Create it.
      $field = array(
        'field_name' => $name,
        'type' => $type,
      );
      drush_op('field_create_field', $field);
    }

    // Create the instance.
    $instance = array(
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    );
    if ($widget) {
      $instance['widget'] = array('type' => $widget);
    }
    drush_op('field_create_instance', $instance);

    $urls[] = url(drush_field_ui_bundle_admin_path($entity_type, $bundle) . '/fields/' . $name, array('absolute' => TRUE));
  }
  drush_print(implode(' ', $urls));
}

// Copy of function _field_ui_bundle_admin_path() since we don't want to load UI module.
function drush_field_ui_bundle_admin_path($entity_type, $bundle_name) {
  $bundles = field_info_bundles($entity_type);
  $bundle_info = $bundles[$bundle_name];
  if (isset($bundle_info['admin'])) {
    return isset($bundle_info['admin']['real path']) ? $bundle_info['admin']['real path'] : $bundle_info['admin']['path'];
  }
}

function drush_field_update($field_name) {
   $info = field_info_field($field_name);
   foreach ($info['bundles'] as $entity_type => $bundles) {
     foreach ($bundles as $bundle) {
       $urls[] = url(drush_field_ui_bundle_admin_path($entity_type, $bundle) . '/fields/' . $field_name, array('absolute' => TRUE));
     }
   }
  drush_print(implode(' ', $urls));
}

function drush_field_delete($field_name) {
  $info = field_info_field($field_name);
  $confirm = TRUE;

  if (!$bundle = drush_get_option('bundle')) {
    foreach ($info['bundles'] as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $all_bundles[] = $bundle;
      }
    }
    if (count($bundles) > 1) {
      $options = array_merge(array('all' => dt('All bundles')), drupal_map_assoc($bundles));
      $bundle = drush_choice($options, dt("Choose a particular bundle or 'All bundles'"));
      $confirm = FALSE;
    }
    else {
      if (!drush_confirm(dt('Do you want to delete the %field_name field?', array('%field_name' => $field_name)))) {
        return drush_user_abort();
      }
    }
  }

  if ($bundle == 'all') {
    foreach ($info['bundles'] as $entity_type => $bundles) {
       foreach ($bundles as $bundle) {
         $instance = field_info_instance($entity_type, $field_name, $bundle);
         drush_op('field_delete_instance', $instance);
       }
     }
  }
  else {
    $entity_type = drush_field_get_entity_from_bundle($bundle);
    $instance = field_info_instance($entity_type, $field_name, $bundle);
    drush_op('field_delete_instance', $instance);
  }

  // If there are no more bundles, delete the field.
  $info = field_info_field($field_name);
  if (empty($info['bundles'])) {
    drush_op('field_delete_field', $field_name);
  }
}

function drush_field_clone($source_field_name, $target_field_name) {
   if (!$info = field_info_field($source_field_name)) {
     return drush_set_error(dt('%source not found in field list.', array('%source' => $source_field_name)));
   }

   unset($info['id']);
   $info['field_name'] = $target_field_name;
   $target = drush_op('field_create_field', $info);

   foreach ($info['bundles'] as $entity_type => $bundles) {
     foreach ($bundles as $bundle) {
       $instance = field_info_instance($entity_type, $source_field_name, $bundle);
       $instance['field_name'] = $target_field_name;
       unset($instance['id']);
       $instance['field_id'] = $target['id'];
       drush_op('field_create_instance', $instance);
       $urls[] = url(drush_field_ui_bundle_admin_path($entity_type, $bundle) . '/fields/' . $target_field_name, array('absolute' => TRUE));
     }
   }

  drush_print(implode(' ', $urls));
}

function drush_field_info($type = NULL) {
  if (is_null($type)) {
    $type = drush_choice(drupal_map_assoc(array('types', 'fields')), dt('Which information do you wish to see?'));
  }

  switch ($type) {
    case 'fields':
      $rows[] = array(
        dt('Field name'),
        dt('Field type'),
        dt('Bundles'),
      );
      $info = field_info_fields();
      foreach ($info as $field_name => $field) {
        $bundle_strs = array();
        foreach ($field['bundles'] as $entity_type => $bundles) {
          $bundle_strs[] = implode(',', $bundles);
        }
        $row = array(
          $field_name,
          $field['type'],
          implode(' ', $bundle_strs),
        );
        $rows[] = $row;
        $pipe[] = implode(',', $row);
      }
      break;
    case 'types':
      $rows[] = array(
        dt('Field type'),
        dt('Default widget'),
        dt('Widgets'),
      );
      $info = field_info_field_types();
      module_load_include('inc', 'field_ui', 'field_ui.admin');
      $widgets = field_info_widget_types();
      foreach ($info as $type_name => $type) {
        $widgets = field_ui_widget_type_options($type_name);
        $row = array(
          $type_name,
          $type['default_widget'],
          implode(', ', array_keys($widgets)),
        );
        $rows[] = $row;
        $pipe[] = implode(',', $row);
      }
      break;
  }

  drush_print_table($rows, TRUE);
  drush_print_pipe($pipe);
  return $rows;
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

function drush_field_get_entity_from_bundle($bundle) {
  if (drush_get_option('entity_type')) {
    return drush_get_option('entity_type');
  }
  else {
    $info = field_info_bundles();
    foreach ($info as $entity_type => $bundles) {
      if (isset($bundles[$bundle])) {
        return $entity_type;
      }
    }
  }
}
