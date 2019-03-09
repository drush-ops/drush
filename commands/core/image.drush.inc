<?php

/**
 * @file
 *  Image module's drush integration.
 *
 *  @todo image-build($field_name, $bundle, $style_name)
 */

use Drush\Log\LogLevel;

/**
 * Implementation of hook_drush_command().
 */
function image_drush_command() {
  $items['image-flush'] = array(
    'description' => 'Flush all derived images for a given style.',
    'core' => array('7+'),
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
    'aliases' => array('if', 'image:flush'),
  );
  $items['image-derive'] = array(
    'description' => 'Create an image derivative.',
    'core' => array('7+'),
    'drupal dependencies' => array('image'),
    'arguments' => array(
      'style' => 'An image style machine name.',
      'source' => 'Path to a source image. Optionally prepend stream wrapper scheme.',
    ),
    'required arguments' => TRUE,
    'options' => array(),
    'examples' => array(
      'drush image-derive thumbnail themes/bartik/logo.png' => 'Save thumbnail sized derivative of logo image.',
    ),
    'aliases' => array('id', 'image:derive'),
  );
  return $items;
}

/**
 * Implements hook_drush_help_alter().
 */
function image_drush_help_alter(&$command) {
  // Drupal 8+ customizations.
  if ($command['command'] == 'image-derive' && drush_drupal_major_version() >= 8) {
    unset($command['examples']);
    $command['examples']['drush image-derive thumbnail core/themes/bartik/logo.png'] = 'Save thumbnail sized derivative of logo image.';
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Array of available configuration files for editing.
 */
function image_image_flush_complete() {
  drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
  drush_include_engine('drupal', 'image');
  return array('values' => array_keys(drush_image_styles()));
}

function drush_image_flush_pre_validate($style_name = NULL) {
  drush_include_engine('drupal', 'image');
  if (!empty($style_name) && !$style = drush_image_style_load($style_name)) {
    return drush_set_error(dt('Image style !style not recognized.', array('!style' => $style_name)));
  }
}

function drush_image_flush($style_name = NULL) {
  drush_include_engine('drupal', 'image');
  if (drush_get_option('all')) {
    $style_name = 'all';
  }

  if (empty($style_name)) {
    $styles = array_keys(drush_image_styles());
    $choices = array_combine($styles, $styles);
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
    drush_log(dt('All image styles flushed'), LogLevel::SUCCESS);
  }
  else {
    drush_image_flush_single($style_name);
  }
}

function drush_image_derive_validate($style_name, $source) {
  drush_include_engine('drupal', 'image');
  if (!$style = drush_image_style_load($style_name)) {
    return drush_set_error(dt('Image style !style not recognized.', array('!style' => $style_name)));
  }

  if (!file_exists($source)) {
    return drush_set_error(dt('Source file not found - !file.', array('!file' => $source)));
  }
}

/*
 * Command callback. Create an image derivative.
 *
 * @param string $style_name
 *   The name of an image style.
 *
 * @param string $source
 *   The path to a source image, relative to Drupal root.
 */
function drush_image_derive($style_name, $source) {
  drush_include_engine('drupal', 'image');
  return _drush_image_derive($style_name, $source);
}
