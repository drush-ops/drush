<?php

use Drupal\Core\Render\Markup;
use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Utils\StringUtils;

/**
 * @defgroup outputfunctions Process output text.
 * @{
 */

/**
 * Prints a message with optional indentation. In general,
 * $this->>logger()->$method($message) is often a better choice than this function.
 * That gets your confirmation message (for example) into the logs for this
 * drush request. Consider that Drush requests may be executed remotely and
 * non interactively.
 *
 * @param $message
 *   The message to print.
 * @param $indent
 *    The indentation (space chars)
 * @param $handle
 *    File handle to write to.  NULL will write
 *    to standard output, STDERR will write to the standard
 *    error.  See http://php.net/manual/en/features.commandline.io-streams.php
 * @param $newline
 *    Add a "\n" to the end of the output.  Defaults to TRUE.
 */
function drush_print($message = '', $indent = 0, $handle = NULL, $newline = TRUE) {
  $msg = str_repeat(' ', $indent) . (string)$message;
  if ($newline) {
    $msg .= "\n";
  }
  if (($charset = drush_get_option('output_charset')) && function_exists('iconv')) {
    $msg = iconv('UTF-8', $charset, $msg);
  }
  if (isset($handle)) {
    fwrite($handle, $msg);
  }
  else {
    print $msg;
    //$output = Drush::service('output');
    //$output->write($msg);
  }
}

/**
 * Print a prompt -- that is, a message with no trailing newline.
 */
function drush_print_prompt($message, $indent = 0, $handle = NULL) {
  drush_print($message, $indent, $handle, FALSE);
}

/**
 * Rudimentary replacement for Drupal API t() function.
 *
 * @param string
 *   String to process, possibly with replacement item.
 * @param array
 *  An associative array of replacement items.
 *
 * @return
 *   The processed string.
 *
 * @see t()
 */
function dt($string, $args = array()) {
  $output = NULL;
  if (function_exists('t') && drush_drupal_major_version() == 7) {
    $output = t($string, $args);
  }
  // The language system requires a working container which has the string
  // translation service.
  else if (drush_drupal_major_version() >= 8 && \Drupal::hasService('string_translation')) {
    // Drupal 8 removes !var replacements, creating a user-level error when
    // these are used, so we'll pre-replace these before calling translate().
    list($string, $args) = replace_legacy_dt_args($string, $args);
    $output = (string) \Drupal::translation()->translate($string, $args);
  }
  else if (function_exists('t') && drush_drupal_major_version() <= 7 && function_exists('theme')) {
    $output = t($string, $args);
  }

  // If Drupal's t() function unavailable.
  if (!isset($output)) {
    $output = StringUtils::interpolate($string, $args);
  }
  return $output;
}

/**
 * Replace placeholders that begin with a '!' with '@'.
 */
function replace_legacy_dt_args(&$string, &$legacy_args) {
  $args = array();
  $replace = array();
  foreach ($legacy_args as $name => $argument) {
    if ($name[0] == '!') {
      $new_arg = '@' . substr($name, 1);
      $replace[$name] = $new_arg;
      $args[$new_arg] = Markup::create($argument);
    }
    else {
      $args[$name] = $argument;
    }
  }
  return [
    strtr($string, $replace),
    $args
  ];
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
  $replacements = array(
    '<hr>' => '------------------------------------------------------------------------------',
    '<li>' => '  * ',
    '<h1>' => '===== ',
    '</h1>' => ' =====',
    '<h2>' => '---- ',
    '</h2>' => ' ----',
    '<h3>' => '::: ',
    '</h3>' => ' :::',
    '<br/>' => "\n",
  );
  $text = str_replace(array_keys($replacements), array_values($replacements), $html);
  return html_entity_decode(preg_replace('/ *<[^>]*> */', ' ', $text));
}


/**
 * Converts a PHP variable into its Javascript equivalent.
 *
 * We provide a copy of D7's drupal_json_encode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_decode()
 * @ingroup php_wrappers
 */
function drush_json_encode($var) {
  $json = json_encode($var, JSON_PRETTY_PRINT);
  // json_encode() does not escape <, > and &, so we do it with str_replace().
  return str_replace(array('<', '>', '&'), array('\u003c', '\u003e', '\u0026'), $json);
}

/**
 * Converts an HTML-safe JSON string into its PHP equivalent.
 *
 * We provide a copy of D7's drupal_json_decode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_encode()
 * @ingroup php_wrappers
 */
function drush_json_decode($var) {
  return json_decode($var, TRUE);
}

/**
 * @} End of "defgroup outputfunctions".
 */
