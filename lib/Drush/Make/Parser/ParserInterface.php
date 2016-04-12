<?php

/**
 * @file
 * Interface for make file parsing.
 */

namespace Drush\Make\Parser;

interface ParserInterface {

  /**
   * Determine if a given file is supported.
   *
   * @param string $filename
   *
   * @return bool
   */
  public static function supportedFile($filename);

  /**
   * Parse an input string into an array.
   *
   * @param string $data
   *
   * @return array
   *   Makefile data as an array.
   */
  public static function parse($data);

}
