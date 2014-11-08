<?php

namespace Drush\Log;

use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class DrushLog implements LoggerInterface {

  use RfcLoggerTrait;

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
      case LogLevel::ALERT:
      case LogLevel::CRITICAL:
      case LogLevel::EMERGENCY:
      case LogLevel::ERROR:
        $error_type = 'error';
        break;

      case RfcLogLevel::WARNING:
      case LogLevel::WARNING:
        $error_type = 'warning';
        break;

      case RfcLogLevel::DEBUG:
      case RfcLogLevel::INFO:
      case RfcLogLevel::NOTICE:
      case LogLevel::DEBUG:
      case LogLevel::INFO:
      case LogLevel::NOTICE:
        $error_type = 'notice';
        break;

      default:
        $error_type = $level;
        break;
    }
    drush_log($message, $error_type);
  }

}
