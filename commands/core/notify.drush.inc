<?php
/**
 * @file
 * Add system notifications as a new drush option.
 */

/**
 * @todo there are no hooks fired after a command errors out.
 */
register_shutdown_function('drush_notify_shutdown');

/**
 * Implements hook_drush_help_alter().
 */
function notify_drush_help_alter(&$command) {
  if ($command['command'] == 'global-options') {
    // Do not include these in options in standard help.
    if ($command['#brief'] === FALSE) {
      $command['options']['notify'] = array(
        'description' => 'Use system notifications to signal command completion. If set to a number, commands that finish in fewer seconds will not trigger a notification.',
        'example-value' => 60,
        'never-propagate' => TRUE,
      );
      $command['options']['notify-audio'] = array(
        'description' => 'Trigger an audio alert to signal command completion. If set to a number, commands that finish in fewer seconds will not trigger a notification.',
        'example-value' => 60,
        'never-propagate' => TRUE,
      );
      $command['sub-options']['notify']['notify-cmd'] = array(
        'description' => 'Specify the shell command to trigger the notification.',
        'never-propagate' => TRUE,
      );
      $command['sub-options']['notify']['notify-cmd-audio'] = array(
        'description' => 'Specify the shell command to trigger the audio notification.',
        'never-propagate' => TRUE,
      );
    }
  }
}

/**
 * Implements hook_drush_help().
 */
function notify_drush_help($section) {
  switch ($section) {
    case 'notify:cache-clear':
      return dt('Caches have been cleared.');
    case 'notify:site-install:error':
      return dt('Failed on site installation');
  }
}

/**
 * Shutdown function to signal on errors.
 */
function drush_notify_shutdown() {
  $cmd = drush_get_command();

  if (empty($cmd['command'])) {
    return;
  }

  // pm-download handles its own notification.
  if ($cmd['command'] != 'pm-download' && drush_notify_allowed($cmd['command'])) {
    $msg = dt("Command '!command' completed.", array('!command' => $cmd['command']));
    drush_notify_send(drush_notify_command_message($cmd['command'], $msg));
  }

  if (drush_get_option('notify', FALSE) && drush_get_error()) {
    // If the only error is that notify failed, do not try to notify again.
    $log = drush_get_error_log();
    if (count($log) == 1 && array_key_exists('NOTIFY_COMMAND_NOT_FOUND', $log)) {
      return;
    }

    // Send an alert that the command failed.
    if (drush_notify_allowed($cmd['command'])) {
      $msg = dt("Command '!command' failed.", array('!command' => $cmd['command']));
      drush_notify_send(drush_notify_command_message($cmd['command'] . ':error', $msg));
    }
  }
}

/**
 * Determine the message to send on command completion.
 *
 * @param string $command
 *   Name of the Drush command for which we check message overrides.
 * @param string $default
 *   (Default: NULL) Default message to use if there are not notification message overrides.
 *
 * @return string
 *   Message to use for notification.
 */
function drush_notify_command_message($command, $default = NULL) {
  if ($msg = drush_command_invoke_all('drush_help', 'notify:' . $command)) {
    $msg = implode("\n", $msg);
  }
  else {
    $msg = $default ? $default : $msg = $command . ': No news is good news.';
  }

  return $msg;
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
function drush_notify_send($msg) {
  drush_notify_send_text($msg);
  if (drush_get_option('notify-audio', FALSE)) {
    drush_notify_send_audio($msg);
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
function drush_notify_send_text($msg) {
  $override = drush_get_option('notify-cmd', FALSE);

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
    return drush_set_error('NOTIFY_COMMAND_NOT_FOUND', $error_message . ' ' . dt('Or you may specify an alternate command to run by specifying --notify-cmd=<my_command>'));
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
function drush_notify_send_audio($msg) {
  $override = drush_get_option('notify-cmd-audio', FALSE);

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
        $cmd = drush_get_option('notify-cmd-audio', 'spd-say') . ' %s';
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
function drush_notify_allowed($command) {
  $notify = drush_get_option(array('notify', 'notify-audio'), FALSE);
  $execution = time() - $_SERVER['REQUEST_TIME'];

  return ($notify === TRUE ||
    (is_numeric($notify) && $notify > 0 && $execution > $notify));
}

