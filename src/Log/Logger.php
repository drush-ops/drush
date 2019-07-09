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

use Robo\Log\RoboLogger;
use Symfony\Component\Console\Output\OutputInterface;

class Logger extends RoboLogger
{

    public function __construct(OutputInterface $output)
    {
        parent::__construct($output);
    }

    public function log($level, $message, array $context = [])
    {
        // @todo Get rid of custom log levels entirely? See \Drush\Log\LogLevel.

        // Save the original level, then map it to a standard log level.
        $context['_level'] = $level;
        switch ($level) {
            case LogLevel::CANCEL:
                $level = LogLevel::WARNING;
                break;
            case LogLevel::SUCCESS:
            case LogLevel::OK:
                $level = LogLevel::NOTICE;
                break;
            case LogLevel::PREFLIGHT:
            case LogLevel::BOOTSTRAP:
            case LogLevel::DEBUG_NOTIFY:
                $level = LogLevel::DEBUG;
                break;
            default:
                $level = LogLevel::DEBUG;
                break;
        }

      // Robo-styled output
        parent::log($level, $message, $context);
    }
}
