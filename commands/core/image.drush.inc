<?php

/**
 * @file
 *  Image module's drush integration.
 *
 *  @todo image-build($field_name, $bundle, $style_name)
 */

/**
 * Implementation of hook_drush_command().
 */
function image_drush_command() {
  $items['image-flush'] = array(
    'description' => 'Flush all derived images for a given style.',
    'core' => array('7+'),
    'drupal_dependencies' => array('image'),
    'arguments' => array(
      'style' => 'An image style machine name. If not provided, user may choose from a list of names.',
    ),
    'options' => array(
      'all' => 'Flush all derived images',
    ),
    'examples' => array(
      'drush image-flush' => 'Pick an image style and then delete its images.',
      'drush image-flush thumbnail' => 'Delete all thumbnail images.',
      'drush image-flush --all' => 'Flush all derived images. They will be regenerated on the fly.',
    ),
  );
  return $items;
}

function drush_image_flush($style_name = NULL) {
  if (drush_get_option('all')) {
    drush_image_flush_all();
  }
  elseif (empty($style_name)) {
    $choices = drupal_map_assoc(array_keys(image_styles()));
    $choices = array_merge(array('all' => 'all'), $choices);
    $style_name = drush_choice($choices, dt("Choose a style to flush."));
    if ($style_name == 'all') {
      drush_image_flush_all();
    }
    else {
      $commands = drush_get_commands();
      return drush_dispatch($commands['image-flush'], array($style_name));
    }
  }
  else {
    if ($style = image_style_load($style_name)) {
      image_style_flush($style);
      drush_log(dt('Image style !style_name flushed', array('!style_name' => $style_name)), 'success');
    }
    else {
      return drush_set_error(dt('Image style !style not recognized.', array('!style' => $style_name)));
    }
  }
}

function drush_image_flush_all() {
  foreach (image_styles() as $style) {
    image_style_flush($style);
  }
  drush_log(dt('All image styles flushed'), 'success');
}
