<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

/**
 * @todo there are no hooks fired after a command errors out. Still?
 */

class NotifyCommands extends DrushCommands {

  /**
   *
   *
   * @hook pre-command *
   * @option notify Use system notifications to signal command completion. If set to a number, commands that finish in fewer seconds will not trigger a notification.
   * @todo change these to sub-options when/if we support those again.
   * @option notify-audio Trigger an audio alert to signal command completion. If set to a number, commands that finish in fewer seconds will not trigger a notification.
   * @option notify-cmd Specify the shell command to trigger the notification.
   * @option notify-cmd-audio Specify the shell command to trigger the audio notification.
   * @todo hidden is not yet part of annotated-command project. It is recognized by Drush's annotation_adapter.inc
   * @hidden-option notify,notify-audio,notify-cmd,notify-cmd-audio
   */
  public function notify(CommandData $commandData) {
    register_shutdown_function([$this, 'shutdown'], $commandData);
  }

  public function shutdown(CommandData $commandData) {
    $cmd = $commandData->input()->getFirstArgument();

    if (empty($cmd)) {
      return;
    }

    // pm-download handles its own notification.
    if ($cmd != 'pm-download' && self::isAllowed($commandData)) {
      $msg = $commandData->annotationData()->get('notify', dt("Command '!command' completed.", array('!command' => $cmd)));
      $this->shutdownSend($msg, $commandData);
    }

    if ($commandData->input()->getOption('notify') && drush_get_error()) {
      // If the only error is that notify failed, do not try to notify again.
      $log = drush_get_error_log();
      if (count($log) == 1 && array_key_exists('NOTIFY_COMMAND_NOT_FOUND', $log)) {
        return;
      }

      // Send an alert that the command failed.
      if (self::isAllowed($commandData)) {
        $msg = dt("Command '!command' failed.", array('!command' => $cmd));
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
  function shutdownSend($msg, CommandData $commandData) {
    $this->shutdownSendText($msg, $commandData);
    if ($commandData->input()->getOption('notify-audio')) {
      $this->shutdownSendAudio($msg, $commandData);
    }
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
  function shutdownSendText($msg,CommandData $commandData) {
    $override = $commandData->input()->getOption('notify-cmd');

    if (!empty($override)) {
      $cmd = $override;
    }
    else {
      switch (PHP_OS) {
        case 'Darwin':
          $cmd = 'terminal-notifier -message %s -title Drush';
          $error_message = dt('terminal-notifier command failed. Please install it from https://github.com/alloy/terminal-notifier.');
          break;
        case 'Linux':
        default:
          $icon = drush_normalize_path(DRUSH_BASE_PATH . '/drush_logo-black.png');
          $cmd = "notify-send %s -i $icon";
          $error_message = dt('notify-send command failed. Please install it as per http://coderstalk.blogspot.com/2010/02/how-to-install-notify-send-in-ubuntu.html.');
          break;
      }
    }

    if (!drush_shell_exec($cmd, $msg)) {
      throw new \Exception($error_message . ' ' . dt('Or you may specify an alternate command to run by specifying --notify-cmd=<my_command>'));
    }

    return TRUE;
  }

  /**
   * Send an audio-based system notification.
   *
   * This function is only automatically invoked with the additional use of the
   * --notify-audio flag or configuration state.
   *
   * @param $msg
   *   Message for audio recital.
   *
   * @return bool
   *   TRUE on success, FALSE on failure
   */
  function shutdownSendAudio($msg, CommandData $commandData) {
    $override = $commandData->input()->getOption('notify-cmd-audio');

    if (!empty($override)) {
      $cmd = $override;
    }
    else {
      switch (PHP_OS) {
        case 'Darwin':
          $cmd = 'say %s';
          break;
        case 'Linux':
        default:
          $cmd = 'spd-say' . ' %s';
      }
    }

    if (!drush_shell_exec($cmd, $msg)) {
      return drush_set_error('NOTIFY_COMMAND_NOT_FOUND', dt('The third party notification utility failed.'));
    }
  }

  /**
   * Identify if the given Drush request should trigger a notification.
   *
   * @param $command
   *   Name of the command.
   *
   * @return
   *   Boolean
   */
  static function isAllowed(CommandData $commandData) {
    $notify = $commandData->input()->getOption('notify') || $commandData->input()->getOption('notify-audio');
    $execution = time() - $_SERVER['REQUEST_TIME'];

    return ($notify === TRUE ||
      (is_numeric($notify) && $notify > 0 && $execution > $notify));
  }
}