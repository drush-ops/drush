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
// @todo: finish this.
//  $items['image-create'] = array(
//    'description' => 'Create an image derivative.',
//    'core' => array('7+'),
//    'drupal_dependencies' => array('image'),
//    'arguments' => array(
//      'style' => 'An image style machine name. If not provided, user may choose from a list of names.',
//      'source' => 'Path to a source image.',
//    ),
//    'required arguments' => TRUE,
//    'options' => array(),
//    'examples' => array(),
//  );
  return $items;
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Array of available configuration files for editing.
 */
function image_image_flush_complete() {
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
  drush_include_engine('drupal', 'image', drush_drupal_major_version());
  return array('values' => array_keys(drush_image_styles()));
}

function drush_image_flush_pre_validate($style_name = NULL) {
  if (!empty($style_name) && !$style = image_style_load($style_name)) {
    return drush_set_error(dt('Image style !style not recognized.', array('!style' => $style_name)));
  }
}

function drush_image_flush($style_name = NULL) {
  drush_include_engine('drupal', 'image', drush_drupal_major_version());
  if (drush_get_option('all')) {
    $style_name = 'all';
  }

  if (empty($style_name)) {
    $choices = drupal_map_assoc(array_keys(drush_image_styles()));
    $choices = array_merge(array('all' => 'all'), $choices);
    $style_name = drush_choice($choices, dt("Choose a style to flush."));
    if ($style_name === FALSE) {
      return drush_user_abort();
    }
  }

  if ($style_name == 'all') {
    foreach (drush_image_styles() as $style_name => $style) {
      drush_image_flush_single($style_name);
    }
    drush_log(dt('All image styles flushed'), 'success');
  }
  else {
    drush_image_flush_single($style_name);
  }
}

function drush_image_create_validate($stylename, $source) {
  if (!$style = image_style_load($style_name)) {
    return drush_set_error(dt('Image style !style not recognized.', array('!style' => $style_name)));
  }

  if (!file_exists($source)) {
    return drush_set_error(dt('Source file not found - !file.', array('!file' => $source)));
  }
}

/*
 * Untested. Please help.
 */
function drush_image_create($stylename, $source) {
  $image_style = image_style_load($style_name);
  if (drush_drupal_major_version() >= 8) {
    $destination = $image_style->buildUri($source);
    $return = $image_style->createDerivative($source, $destination);
  }
  else {
    // @todo figure out destination.
    $return = image_style_create_derivative($image_style, $source, $destination);
  }
  return $return;
}
