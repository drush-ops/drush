<?php

/**
 * @file
 * Parser for INI format.
 */

namespace Drush\Make\Parser;

class ParserIni implements ParserInterface {

  /**
   * Regex for parsing INI format.
   */
  private static $iniRegex = '
    @^\s*                           # Start at the beginning of a line, ignoring leading whitespace
    ((?:
      [^=;\[\]]|                    # Key names cannot contain equal signs, semi-colons or square brackets,
      \[[^\[\]]*\]                  # unless they are balanced and not nested
    )+?)
    \s*=\s*                         # Key/value pairs are separated by equal signs (ignoring white-space)
    (?:
      ("(?:[^"]|(?<=\\\\)")*")|     # Double-quoted string, which may contain slash-escaped quotes/slashes
      (\'(?:[^\']|(?<=\\\\)\')*\')| # Single-quoted string, which may contain slash-escaped quotes/slashes
      ([^\r\n]*?)                   # Non-quoted string
    )\s*$                           # Stop at the next end of a line, ignoring trailing whitespace
    @msx';

  /**
   * {@inheritdoc}
   */
  public static function supportedFile($filename) {
    $info = pathinfo($filename);
    return isset($info['extension']) && $info['extension'] === 'make';
  }

  /**
   * {@inheritdoc}
   */
  public static function parse($data) {
    if (preg_match_all(self::$iniRegex, $data, $matches, PREG_SET_ORDER)) {
      $info = array();
      foreach ($matches as $match) {
        // Fetch the key and value string.
        $i = 0;
        foreach (array('key', 'value1', 'value2', 'value3') as $var) {
          $$var = isset($match[++$i]) ? $match[$i] : '';
        }
        $value = stripslashes(substr($value1, 1, -1)) . stripslashes(substr($value2, 1, -1)) . $value3;

        // Parse array syntax.
        $keys = preg_split('/\]?\[/', rtrim($key, ']'));
        $last = array_pop($keys);
        $parent = &$info;

        // Create nested arrays.
        foreach ($keys as $key) {
          if ($key == '') {
            $key = count($parent);
          }
          if (isset($merge_item) && isset($parent[$key]) && !is_array($parent[$key])) {
            $parent[$key] = array($merge_item => $parent[$key]);
          }
          if (!isset($parent[$key]) || !is_array($parent[$key])) {
            $parent[$key] = array();
          }
          $parent = &$parent[$key];
        }

        // Handle PHP constants.
        if (defined($value)) {
          $value = constant($value);
        }

        // Insert actual value.
        if ($last == '') {
          $last = count($parent);
        }
        if (isset($merge_item) && isset($parent[$last]) && is_array($parent[$last])) {
          $parent[$last][$merge_item] = $value;
        }
        else {
          $parent[$last] = $value;
        }
      }
      return $info;
    }
  }

}
