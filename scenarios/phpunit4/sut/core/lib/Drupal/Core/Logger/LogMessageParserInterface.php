<?php

namespace Drupal\Core\Logger;

/**
 * Defines an interface for parsing log messages and their placeholders.
 */
interface LogMessageParserInterface {

  /**
   * Parses and transforms message and its placeholders to a common format.
   *
   * For a value to be considered as a placeholder should be in the following
   * formats:
   *   - PSR3 format:
   *     @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message
   *   - Drupal specific string placeholder format:
   *     @see \Drupal\Component\Render\FormattableMarkup
   *
   * Values in PSR3 format will be transformed to
   * \Drupal\Component\Render\FormattableMarkup format.
   *
   * @param string $message
   *   The message that contains the placeholders.
   *   If the message is in PSR3 style, it will be transformed to
   *   \Drupal\Component\Render\FormattableMarkup style.
   * @param array $context
   *   An array that may or may not contain placeholder variables.
   *
   * @return array
   *   An array of the extracted message placeholders.
   */
  public function parseMessagePlaceholders(&$message, array &$context);

}
