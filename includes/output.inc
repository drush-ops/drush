<?php

use Drupal\Core\Render\Markup;
use Drush\Utils\StringUtils;

/**
 * @defgroup outputfunctions Process output text.
 * @{
 */

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

/**
 * Convert html to readable text.  Compatible API to
 * drupal_html_to_text, but less functional.  Caller
 * might prefer to call drupal_html_to_text if there
 * is a bootstrapped Drupal site available.
 *
 * @param string $html
 *   The html text to convert.
 *
 * @return string
 *   The plain-text representation of the input.
 */
function drush_html_to_text($html, $allowed_tags = NULL) {
  $replacements = [
    '<hr>' => '------------------------------------------------------------------------------',
    '<li>' => '  * ',
    '<h1>' => '===== ',
    '</h1>' => ' =====',
    '<h2>' => '---- ',
    '</h2>' => ' ----',
    '<h3>' => '::: ',
    '</h3>' => ' :::',
    '<br/>' => "\n",
  ];
  $text = str_replace(array_keys($replacements), array_values($replacements), $html);
  return html_entity_decode(preg_replace('/ *<[^>]*> */', ' ', $text));
}

/**
 * @} End of "defgroup outputfunctions".
 */
