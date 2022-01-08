<?php

namespace Drush\Runtime;

use Drush\Drush;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Log PHP errors to the Drush log. This is in effect until Drupal's error
 * handler takes over.
 */
class ErrorHandler implements LoggerAwareInterface, HandlerInterface
{
    use LoggerAwareTrait;

    public function installHandler(): void
    {
        set_error_handler([$this, 'errorHandler']);
    }

    public function errorHandler($errno, $message, $filename, $line)
    {
        // E_DEPRECATED was added in PHP 5.3. Drupal 6 will not fix all the
        // deprecated errors, but suppresses them. So we suppress them as well.
        if (defined('E_DEPRECATED')) {
            $errno &= ~E_DEPRECATED;
        }

        // "error_reporting" is usually set in php.ini, but may be changed by
        // drush_errors_on() and drush_errors_off().
        if ($errno & error_reporting()) {
            // By default we log notices.
            $type = Drush::config()->get('runtime.php.notices', LogLevel::INFO);
            $halt_on_error = Drush::config()->get('runtime.php.halt-on-error', true);

            // Bitmask value that constitutes an error needing to be logged.
            $error = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
            if ($errno & $error) {
                $type = 'error';
            }

            // Bitmask value that constitutes a warning being logged.
            $warning = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING;
            if ($errno & $warning) {
                $type = LogLevel::WARNING;
            }

            $this->logger->log($type, $message . ' ' . basename($filename) . ':' . $line);

            if ($errno == E_RECOVERABLE_ERROR && $halt_on_error) {
                $this->logger->error(dt('E_RECOVERABLE_ERROR encountered; aborting. To ignore recoverable errors, run again with --halt-on-error=0'));
                exit(DRUSH_APPLICATION_ERROR);
            }

            return true;
        }
    }
}
