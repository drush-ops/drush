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

use Robo\Robo;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drush\Drush;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Redirects Drupal logging messages to Drush log.
 *
 * Note that Drupal extends the LoggerInterface, and
 * needlessly replaces Psr\Log\LogLevels with Drupal\Core\Logger\RfcLogLevel.
 * Doing this arguably violates the Psr\Log contract,
 * but we can't help that here -- we just need to convert back.
 */
class DrushLog implements LoggerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use RfcLoggerTrait;

    /**
     * The message's placeholders parser.
     *
     * @var LogMessageParserInterface
     */
    protected $parser;

    /**
     * Constructs a DrushLog object.
     *
     * @param LogMessageParserInterface $parser
     *   The parser to use when extracting message variables.
     */
    public function __construct(LogMessageParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        // Only log during Drush requests, not web requests.
        if (!Robo::hasContainer()) {
            return;
        }

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
                $error_type = LogLevel::ERROR;
                break;

            case RfcLogLevel::WARNING:
                $error_type = LogLevel::WARNING;
                break;

            case RfcLogLevel::DEBUG:
                $error_type = LogLevel::DEBUG;
                break;

            case RfcLogLevel::INFO:
                $error_type = LogLevel::INFO;
                break;

            case RfcLogLevel::NOTICE:
                $error_type = LogLevel::NOTICE;
                break;
            // Unknown log levels that are not defined
            // in Psr\Log\LogLevel SHOULD NOT be used.  See
            // https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
            default:
                $error_type = $level;
                break;
        }

        // Populate the message placeholders and then replace them in the message.
        $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);

        // Filter out any placeholders that can not be cast to strings.
        $message_placeholders = array_filter($message_placeholders, function ($element) {
            return is_scalar($element) || is_callable([$element, '__toString']);
        });

        $message = empty($message_placeholders) ? $message : strtr($message, $message_placeholders);

        Drush::logger()->log($error_type, $message, $context);
    }
}
