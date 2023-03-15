<?php

declare(strict_types=1);

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
     */
    protected LogMessageParserInterface $parser;

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
        $error_type = match ($level) {
            RfcLogLevel::ALERT, RfcLogLevel::CRITICAL, RfcLogLevel::EMERGENCY, RfcLogLevel::ERROR => LogLevel::ERROR,
            RfcLogLevel::WARNING => LogLevel::WARNING,
            RfcLogLevel::DEBUG => LogLevel::DEBUG,
            RfcLogLevel::INFO => LogLevel::INFO,
            RfcLogLevel::NOTICE => LogLevel::NOTICE,
            default => $level,
        };

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
