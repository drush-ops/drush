<?php

namespace Drush\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\LoggerTrait;

class DrushLog implements LoggerInterface {

  use LoggerTrait;

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
      case LogLevel::ALERT:
      case LogLevel::CRITICAL:
      case LogLevel::EMERGENCY:
      case LogLevel::ERROR:
        $error_type = 'error';
        break;

      case LogLevel::WARNING:
        $error_type = 'warning';
        break;

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
