<?php
namespace Drush\Runtime;

/**
 * @file
 * Drush's error handler
 */

use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Commands\DrushCommands;
use Webmozart\PathUtil\Path;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Drush's shutdown handler
 *
 * If the command is being executed with the --backend option, the script
 * will return a json string containing the options and log information
 * used by the script.
 *
 * The command will exit with '0' if it was successfully executed, and the
 * result of Runtime::exitCode() if it wasn't.
 *
 */
class ShutdownHandler implements LoggerAwareInterface, HandlerInterface
{
    use LoggerAwareTrait;

    public function installHandler()
    {
        register_shutdown_function([$this, 'shutdownHandler']);
    }

    public function shutdownHandler()
    {
        // Avoid doing anything if our container has not been initialized yet.
        if (!Drush::hasContainer()) {
            return;
        }

        if (!Drush::config()->get(Runtime::DRUSH_RUNTIME_COMPLETED_NAMESPACE)) {
            Drush::logger()->warning('Drush command terminated abnormally.');
            // Make sure that we will return an error code when we exit,
            // even if the code that got us here did not.
            if (!Runtime::exitCode()) {
                Runtime::setExitCode(DrushCommands::EXIT_FAILURE);
            }
        }

        if (Drush::backend()) {
            drush_backend_output();
        }

        // This way returnStatus() will always be the last shutdown function (unless other shutdown functions register shutdown functions...)
        // and won't prevent other registered shutdown functions (IE from numerous cron methods) from running by calling exit() before they get a chance.
        register_shutdown_function([$this, 'returnStatus']);
    }

    /**
     * @deprecated. This function will be removed in Drush 10. Throw an exception to indicate an error.
     */
    public function returnStatus()
    {
        exit(Runtime::exitCode());
    }
}
