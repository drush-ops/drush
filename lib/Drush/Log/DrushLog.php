<?php

/**
 * @file
 * Contains \Drush\Log\DrushLog.
 */

namespace Drush\Log;

use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;

/**
 * Redirects Drupal logging messages to Drush log.
 */
class DrushLog implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a DrushLog object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(LogMessageParserInterface $parser) {
    $this->parser = $parser;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = array()) {
    // Translate the RFC logging levels into their Drush counterparts, more or
    // less.
    // @todo ALERT, CRITICAL and EMERGENCY are considered show-stopping errors,
    // and they should cause Drush to exit or panic. Not sure how to handle this,
    // though.
    switch ($level) {
      case RfcLogLevel::ALERT:
      case RfcLogLevel::CRITICAL:
      case RfcLogLevel::EMERGENCY:
      case RfcLogLevel::ERROR:
        $error_type = 'error';
        break;

      case RfcLogLevel::WARNING:
        $error_type = 'warning';
        break;

      case RfcLogLevel::DEBUG:
      case RfcLogLevel::INFO:
      case RfcLogLevel::NOTICE:
        $error_type = 'notice';
        break;

      default:
        $error_type = $level;
        break;
    }

    // Populate the message placeholders and then replace them in the message.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);
    drush_log($message, $error_type);
  }

}
