<?php

/**
 * @file
 * Contains \Drush\Log\Logger.
 *
 * This is the actual Logger for Drush that is responsible
 * for logging messages.
 *
 * This logger is designed such that it can be provided to
 * other libraries that log to a Psr\Log\LoggerInterface.
 * As such, it takes responsibility for passing log messages
 * to backend invoke, as necessary (c.f. drush_backend_packet()).
 *
 * Drush supports all of the required log levels from Psr\Log\LogLevel,
 * and also defines its own. See Drush\Log\LogLevel.
 *
 * Those who may wish to change the way logging works in Drush
 * should therefore NOT attempt to replace this logger with their
 * own LoggerInterface, as it will not work.  It would be okay
 * to extend Drush\Log\Logger, or perhaps we could provide a way
 * to set an output I/O object here, in case output redirection
 * was the only thing that needed to be swapped out.
 */

namespace Drush\Log;

use Drush\Log\LogLevel;
use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger {

    public function log($level, $message, array $context = array()) {
      // Convert to old $entry array for b/c calls
      $entry = $context;
      $entry['type'] = $level;
      $entry['message'] = $message;
      if (!isset($entry['memory'])) {
        $entry['memory'] = memory_get_usage();
      }

      // Drush\Log\Logger should take over all of the responsibilities
      // of drush_log, including caching the log messages and sending
      // log messages along to backend invoke.
      // TODO: move these implementations inside this class.
      $log =& drush_get_context('DRUSH_LOG', array());
      $log[] = $entry;
      if ($level != LogLevel::DEBUG_NOTIFY) {
        drush_backend_packet('log', $entry);
      }

      if (drush_get_context('DRUSH_NOCOLOR')) {
        $red = "[%s]";
        $yellow = "[%s]";
        $green = "[%s]";
      }
      else {
        $red = "\033[31;40m\033[1m[%s]\033[0m";
        $yellow = "\033[1;33;40m\033[1m[%s]\033[0m";
        $green = "\033[1;32;40m\033[1m[%s]\033[0m";
      }

      $verbose = drush_get_context('DRUSH_VERBOSE');
      $debug = drush_get_context('DRUSH_DEBUG');
      $debugnotify = drush_get_context('DRUSH_DEBUG_NOTIFY');

      switch ($level) {
        case LogLevel::WARNING :
        case LogLevel::CANCEL :
          $type_msg = sprintf($yellow, $level);
          break;
        case 'failed' : // Obsolete; only here in case contrib is using it.
        case LogLevel::EMERGENCY : // Not used by Drush
        case LogLevel::ALERT : // Not used by Drush
        case LogLevel::ERROR :
          $type_msg = sprintf($red, $level);
          break;
        case LogLevel::OK :
        case 'completed' : // Obsolete; only here in case contrib is using it.
        case LogLevel::SUCCESS :
        case 'status': // Obsolete; only here in case contrib is using it.
          // In quiet mode, suppress progress messages
          if (drush_get_context('DRUSH_QUIET')) {
            return TRUE;
          }
          $type_msg = sprintf($green, $level);
          break;
        case LogLevel::NOTICE :
        case 'message' : // Obsolete; only here in case contrib is using it.
        case LogLevel::INFO :
          if (!$verbose) {
            // print nothing. exit cleanly.
            return TRUE;
          }
          $type_msg = sprintf("[%s]", $level);
          break;
        case LogLevel::DEBUG_NOTIFY :
          $level = LogLevel::DEBUG; // Report 'debug', handle like 'preflight'
        case LogLevel::PREFLIGHT :
          if (!$debugnotify) {
            // print nothing unless --debug AND --verbose. exit cleanly.
            return TRUE;
          }
          $type_msg = sprintf("[%s]", $level);
          break;
        case LogLevel::BOOTSTRAP :
        case LogLevel::DEBUG :
        default :
          if (!$debug) {
            // print nothing. exit cleanly.
            return TRUE;
          }
          $type_msg = sprintf("[%s]", $level);
          break;
      }

      // When running in backend mode, log messages are not displayed, as they will
      // be returned in the JSON encoded associative array.
      if (drush_get_context('DRUSH_BACKEND')) {
        return;
      }

      $columns = drush_get_context('DRUSH_COLUMNS', 80);

      $width[1] = 11;
      // Append timer and memory values.
      if ($debug) {
        $timer = sprintf('[%s sec, %s]', round($entry['timestamp']-DRUSH_REQUEST_TIME, 2), drush_format_size($entry['memory']));
        $entry['message'] = $entry['message'] . ' ' . $timer;
      }

      $width[0] = ($columns - 11);

      $format = sprintf("%%-%ds%%%ds", $width[0], $width[1]);

      // Place the status message right aligned with the top line of the error message.
      $message = wordwrap($entry['message'], $width[0]);
      $lines = explode("\n", $message);
      $lines[0] = sprintf($format, $lines[0], $type_msg);
      $message = implode("\n", $lines);
      drush_print($message, 0, STDERR);

    }


}
