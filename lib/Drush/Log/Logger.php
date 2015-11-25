<?php

/**
 * @file
 * Contains \Drush\Log\Logger.
 *
 * This is the actual Logger for Drush that is responsible
 * for logging messages.
 */

namespace Drush\Log;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger {

    public function log($level, $message, array $context = array()) {
      // Convert to old $entry array for b/c calls
      $entry = $context;
      $entry['type'] = $level;
      $entry['message'] = $message;

      // Drush\Log\Logger should take over all of the responsibilities
      // of drush_log, including caching the log messages and sending
      // log messages along to backend invoke.
      // TODO: move these implementations inside this class.
      $log =& drush_get_context('DRUSH_LOG', array());
      $log[] = $entry;
      drush_backend_packet('log', $entry);

      // Just send this back to _drush_print_log for now.
      // TODO: move _drush_print_log implementation inside this class.
      _drush_print_log($entry);
    }
}
