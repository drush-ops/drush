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

use Drush\Drush;
use Drush\Log\LogLevel;
use Robo\Log\RoboLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Drush\Utils\StringUtils;

class Logger extends RoboLogger
{
    /**
     * Array of logs. For use by backend responses.
     *
     * @var array
     *
     * @deprecated
     */
    protected $logs = [];

    /**
     * Array of error logs. For use by backend responses.
     *
     * @var array
     *
     * @deprecated
     */
    protected $logs_error = [];

    public function __construct(OutputInterface $output)
    {
        parent::__construct($output);
    }

    /**
     * Get an array of logs for the current request.
     *
     * @return array
     *
     * @deprecated Used by drush_backend_output().
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * Get an array of error logs for the current request.
     *
     * @return array
     *
     * @deprecated Used by drush_backend_output().
     */
    public function getErrorLogs()
    {
        return $this->logs_error;
    }

    /**
     * Empty log collections.
     *
     * @deprecated
     */
    public function clearLogs()
    {
        $this->logs_error = $this->logs = [];
    }

    public function log($level, $message, array $context = [])
    {
        $entry = $this->buildEntry($level, $message, $context);

        if (Drush::backend()) {
            $this->logs[] = $entry;
        }

        if ($level != LogLevel::DEBUG_NOTIFY) {
            drush_backend_packet('log', $entry);
        }

        if ($this->output->isDecorated()) {
            $red = "\033[31;40m\033[1m[%s]\033[0m";
            $yellow = "\033[1;33;40m\033[1m[%s]\033[0m";
            $green = "\033[1;32;40m\033[1m[%s]\033[0m";
        } else {
            $red = "[%s]";
            $yellow = "[%s]";
            $green = "[%s]";
        }

        $verbose = \Drush\Drush::verbose();
        $debug = Drush::debug();
        $debugnotify = drush_get_context('DRUSH_DEBUG_NOTIFY');

        $oldStyleEarlyExit = drush_get_context('DRUSH_LEGACY_CONTEXT');

        // Save the original level in the context name, then
        // map it to a standard log level.
        $context['name'] = $level;
        switch ($level) {
            case LogLevel::WARNING:
            case LogLevel::CANCEL:
                $type_msg = sprintf($yellow, $level);
                $level = LogLevel::WARNING;
                break;
            case 'failed': // Obsolete; only here in case contrib is using it.
            case LogLevel::EMERGENCY: // Not used by Drush
            case LogLevel::ALERT: // Not used by Drush
            case LogLevel::ERROR:
                $type_msg = sprintf($red, $level);
                break;
            case LogLevel::OK:
            case 'completed': // Obsolete; only here in case contrib is using it.
            case LogLevel::SUCCESS:
            case 'status': // Obsolete; only here in case contrib is using it.
                // In quiet mode, suppress progress messages
                if ($oldStyleEarlyExit && drush_get_context('DRUSH_QUIET')) {
                    return true;
                }
                $type_msg = sprintf($green, $level);
                $level = LogLevel::NOTICE;
                break;
            case LogLevel::NOTICE:
                $type_msg = sprintf("[%s]", $level);
                break;
            case 'message': // Obsolete; only here in case contrib is using it.
            case LogLevel::INFO:
                if ($oldStyleEarlyExit && !$verbose) {
                    // print nothing. exit cleanly.
                    return true;
                }
                $type_msg = sprintf("[%s]", $level);
                $level = LogLevel::INFO;
                break;
            case LogLevel::DEBUG_NOTIFY:
                $level = LogLevel::DEBUG; // Report 'debug', handle like 'preflight'
            case LogLevel::PREFLIGHT:
                if ($oldStyleEarlyExit && !$debugnotify) {
                    // print nothing unless --debug AND --verbose. exit cleanly.
                    return true;
                }
                $type_msg = sprintf("[%s]", $level);
                $level = LogLevel::DEBUG;
                break;
            case LogLevel::BOOTSTRAP:
            case LogLevel::DEBUG:
            default:
                if ($oldStyleEarlyExit && !$debug) {
                    // print nothing. exit cleanly.
                    return true;
                }
                $type_msg = sprintf("[%s]", $level);
                $level = LogLevel::DEBUG;
                break;
        }

        // When running in backend mode, log messages are not displayed, as they will
        // be returned in the JSON encoded associative array.
        if (\Drush\Drush::backend()) {
            return;
        }

        $columns = drush_get_context('DRUSH_COLUMNS', 80);

        $width[1] = 11;
        // Append timer and memory values.
        if ($debug) {
            $timer = sprintf('[%s sec, %s]', round($entry['timestamp']-DRUSH_REQUEST_TIME, 2), drush_format_size($entry['memory']));
            $entry['message'] = $entry['message'] . ' ' . $timer;
            $message = $message . ' ' . $timer;
        }

      // Robo-styled output
        parent::log($level, $message, $context);
    }

    public function error($message, array $context = [])
    {
        if (Drush::backend()) {
            $this->logs_error[] = $this->buildEntry(LogLevel::ERROR, $message, $context);
        }
        parent::error($message, $context);
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     * @return array
     */
    protected function buildEntry($level, $message, array $context)
    {
        // Convert to old $entry array for b/c calls
        $entry = $context + [
            'type' => $level,
            'message' => StringUtils::interpolate($message, $context),
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(),
        ];
        return $entry;
    }
}
