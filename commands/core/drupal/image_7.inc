<?php

use Drush\Log\LogLevel;

/**
 * @file
 *   Specific functions for a Drupal image handling.
 *   drush_include_engine() magically includes either this file
 *   or image_X.inc depending on which version of Drupal
 *   is in use.
 */

function drush_image_styles() {
  return image_styles();
}

function drush_image_style_load($style_name) {
  return image_style_load($style_name);
}

function drush_image_flush_single($style_name) {
  if ($style = image_style_load($style_name)) {
    image_style_flush($style);
    drush_log(dt('Image style !style_name flushed', array('!style_name' => $style_name)), LogLevel::SUCCESS);
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
function _drush_image_derive($style_name, $source) {
  $image_style = image_style_load($style_name);
  $scheme = file_default_scheme();
  $image_uri = $scheme . '://' . $source;
  $derivative_uri = image_style_path($image_style['name'], $image_uri);
  if (image_style_create_derivative($image_style, $source, $derivative_uri)) {
    return $derivative_uri;
  }
}
