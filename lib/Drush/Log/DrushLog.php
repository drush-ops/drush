<?php

/**
 * @file
 * Contains \Drush\Log\DrushLog.
 *
 * This class is only used to convert logging calls made
 * inside of Drupal into a logging format that is usable
 * by Drush.  This code is ONLY usable within the context
 * of a bootstrapped Drupal 8 site.
 *
 * See Drush\Log\Logger for our actuall LoggerInterface
 * implementation, that does the work of logging messages
 * that originate from Drush.
 */

namespace Drush\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\LoggerTrait;


/**
 * Redirects Drupal logging messages to Drush log.
 *
 * Note that Drupal extends the LoggerInterface, and
 * needlessly replaces Psr\Log\LogLevels with Drupal\Core\Logger\RfcLogLevel.
 * Doing this arguably violates the Psr\Log contract,
 * but we can't help that here -- we just need to convert back.
 */
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

      // TODO: Unknown log levels that are not defined
      // in Psr\Log\LogLevel SHOULD NOT be used.  See
      // https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
      // We should convert these to 'notice'.
      default:
        $error_type = $level;
        break;
    }
    drush_log($message, $error_type);
  }

}
