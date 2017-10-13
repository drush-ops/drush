<?php

use Drupal\Core\Render\Markup;
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
 *
 * @deprecated
 *   Use $this->output()->writeln() in a command method.
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
function dt($string, $args = array()) {
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
 * @} End of "defgroup outputfunctions".
 */
