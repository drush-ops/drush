<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteProcess\Util\Escape;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Webmozart\PathUtil\Path;

class NotifyCommands extends DrushCommands
{
    /**
     * @hook option *
     * @option notify Notify upon command completion. If set to a number, commands that finish in fewer seconds won't notify.
     */
    public function optionsetNotify()
    {
    }

    /**
     * @hook pre-command *
     */
    public function registerShutdown(CommandData $commandData)
    {
        register_shutdown_function([$this, 'shutdown'], $commandData);
    }

    public function shutdown(CommandData $commandData)
    {

        $annotationData = $commandData->annotationData();
        if (!$cmd = $annotationData['command']) {
            return;
        }

        if (Drush::config()->get('notify.duration')) {
            if (self::isAllowed()) {
                $msg = dt("Command '!command' completed.", ['!command' => $cmd]);
                self::shutdownSend($msg, $commandData);
            }
        }
    }

    /**
     * Prepares and dispatches notifications to delivery mechanisms.
     *
     * You may avoid routing a message to secondary messaging mechanisms (e.g. audio),
     * by direct use of the delivery functions.
     *
     * @param string $msg
     *   Message to send via notification.
     */
    public static function shutdownSend($msg, CommandData $commandData)
    {
        self::shutdownSendText($msg, $commandData);
    }

    /**
     * Send text-based system notification.
     *
     * This is the automatic, default behavior. It is intended for use with tools
     * such as libnotify in Linux and Notification Center on OSX.
     *
     * @param string $msg
     *   Message text for delivery.
     *
     * @return bool
     *   TRUE on success, FALSE on failure
     */
    public static function shutdownSendText($msg, CommandData $commandData)
    {
        $override = Drush::config()->get('notify.cmd');

        if (!empty($override)) {
            $cmd = $override;
        } else {
            switch (PHP_OS) {
                case 'Darwin':
                    $cmd = 'terminal-notifier -message %s -title Drush';
                    $error_message = dt('terminal-notifier command failed. Please install it from https://github.com/alloy/terminal-notifier.');
                    break;
                case 'Linux':
                default:
                    $icon = Path::join(DRUSH_BASE_PATH, 'drush_logo-black.png');
                    $cmd = "notify-send %s -i $icon";
                    $error_message = dt('notify-send command failed. Please install it as per http://coderstalk.blogspot.com/2010/02/how-to-install-notify-send-in-ubuntu.html.');
                    break;
            }
        }

        // Keep backward compat and prepare a string here.
        $cmd = sprintf($cmd, Escape::shellArg($msg));
        $process = Drush::process($cmd, $msg);
        $process->run();
        if (!$process->isSuccessful()) {
            Drush::logger()->warning($error_message);
        }

        return true;
    }

    /**
     * Identify if the given Drush request should trigger a notification.
     *
     * @return bool
     */
    public static function isAllowed()
    {
        $duration = Drush::config()->get('notify.duration');
        $execution = time() - $_SERVER['REQUEST_TIME'];

        return ($duration === true ||
        (is_numeric($duration) && $duration > 0 && $execution > $duration));
    }
}
