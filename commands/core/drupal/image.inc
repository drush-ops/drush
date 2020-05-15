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
  return \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();
}

function drush_image_style_load($style_name) {
  return \Drupal::entityTypeManager()->getStorage('image_style')->load($style_name);
}

function drush_image_flush_single($style_name) {
  if ($style = drush_image_style_load($style_name)) {
    $style->flush();
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
  $image_style = drush_image_style_load($style_name);
  $derivative_uri = $image_style->buildUri($source);
  if ($image_style->createDerivative($source, $derivative_uri)) {
    return $derivative_uri;
  }
}
