<?php

use Drush\Utils\StringUtils;

/**
 * Rudimentary translation system, akin to Drupal's t() function.
 *
 * @param string
 *   String to process, possibly with replacement item.
 * @param array
 *  An associative array of replacement items.
 *
 * @return
 *   The processed string.
 */
function dt($string, $args = []) {
  return StringUtils::interpolate($string, $args);
}