<?php

/**
 * @file
 *   Send scrubbed usage data to drush. Omits arguments and option values in order
 *   to assure that no sensitive data is shared. See http://drupal.org/node/1246738.
 */

/**
 * To send usage data, add the following to a .drushrc.php file:
 * $options['drush_usage_log'] = TRUE;
 * $options['drush_usage_send'] = TRUE;
 * $options['drush_usage_size'] = 51200;
*/

function usage_drush_command() {
  $disclaimer = 'Usage statistics contain the Drush command name and the Drush option names, but no arguments or option values.';
  $items['usage-show'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'description' => 'Show Drush usage information that has been logged but not sent.  ' . $disclaimer,
    'examples' => array(
      'drush usage-show' => 'Show cached usage statistics.',
      '$options[\'drush_usage_log\']  = TRUE;' => 'Specify in a .drushrc.php file that usage information should be logged locally in a usage statistics file.',
    ),
    'aliases' => array('ushow'),
  );
  $items['usage-send'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'description' => 'Send anonymous Drush usage information to statistics logging site.  ' . $disclaimer,
    'examples' => array(
      'drush usage-send' => 'Immediately send cached usage statistics.',
      '$options[\'drush_usage_send\']  = TRUE;' => 'Specify in a .drushrc.php file that usage information should be sent.',
      '$options[\'drush_usage_size\']  = 10240;' => 'Specify the frequency (file size) that usage information should be sent.',
    ),
    'aliases' => array('usend'),
  );
  return $items;
}

/**
 * Log and/or send usage data to Mongolab.
 *
 * An organization can implement own hook_drush_exit() to send data to a
 * different endpoint.
 */
function usage_drush_exit() {
  // Ignore statistics for simulated commands. (n.b. in simulated mode, _drush_usage_mongolab will print rather than send statistics)
  if (!drush_get_context('DRUSH_SIMULATE')) {
    $file = _drush_usage_get_file();
    if (drush_get_option('drush_usage_log', FALSE)) {
      _drush_usage_log(drush_get_command(), $file);
    }
    if (drush_get_option('drush_usage_send', FALSE)) {
      _drush_usage_mongolab($file, drush_get_option('drush_usage_size', 51200));
    }
  }
}

function drush_usage_send() {
  $file = _drush_usage_get_file(TRUE);
  if ($file) {
    drush_set_option('drush_usage_send', TRUE);
    drush_set_option('drush_usage_size', 0);
    drush_print(dt('To automatically send anonymous usage data, add the following to a .drushrc.php file: $options[\'drush_usage_send\'] = TRUE;'));
  }
}

function drush_usage_show() {
  $file = _drush_usage_get_file(TRUE);
  if ($file) {
    $json = '[' . file_get_contents($file) . ']';
    $usage_data = json_decode($json);
    foreach ($usage_data as $item) {
      $cmd = $item->cmd;
      $options = (array) $item->opt;
      array_unshift($options, '');
      drush_print($cmd . implode(' --', $options));
    }
  }
}

function _drush_usage_get_file($required = FALSE) {
  $file = drush_directory_cache('usage') . '/usage.txt';
  if (!file_exists($file) && $required) {
    return FALSE; // drush_set_error('DRUSH_NO_USAGE_FILE', dt("No usage file; set $options['drush_usage_log'] = TRUE; in a .drushrc.php file to enable."));
  }
  return $file;
}

function _drush_usage_log($command, $file) {
  drush_merge_engine_data($command);

  // Start out with just the options in the current command record.
  $options = _drush_get_command_options($command);
  // If 'allow-additional-options' contains a list of command names,
  // then union together all of the options from all of the commands.
  if (is_array($command['allow-additional-options'])) {
    $implemented = drush_get_commands();
    foreach ($command['allow-additional-options'] as $subcommand_name) {
      if (array_key_exists($subcommand_name, $implemented)) {
        $options = array_merge($options, _drush_get_command_options($implemented[$subcommand_name]));
      }
    }
  }

  $used = drush_get_merged_options();
  $command_specific = array_intersect(array_keys($used), array_keys($options));
  $record = array(
    'date' => $_SERVER['REQUEST_TIME'],
    'cmd' => $command['command'],
    'opt' => $command_specific,
    'major' => DRUSH_MAJOR_VERSION,
    'minor' => DRUSH_MINOR_VERSION,
    'os' => php_uname('s'),
    'host' => md5(php_uname('n') . get_current_user()),
  );
  $prequel = (file_exists($file)) ? ",\n" : "";
  if (file_put_contents($file, $prequel . json_encode($record), FILE_APPEND)) {
    drush_log(dt('Logged command and option names to local cache.'), 'debug');
  }
  else {
    drush_log(dt('Failed to log command and option names to local cache.'), 'debug');
  }
}

// We only send data periodically to save network traffic and delay. Files
// are sent once they grow over 50KB (configurable).
function _drush_usage_mongolab($file, $min_size_to_send) {
  $json = '[' . file_get_contents($file) . ']';
  if (filesize($file) > $min_size_to_send) {
    $base = 'https://api.mongolab.com/api/1';
    $apikey = '4eb95456e4b0bcd285d8135d'; // submitter account.
    $database = 'usage';
    $collection = 'usage';
    $action = "/databases/$database/collections/$collection";
    $url = $base . $action . "?apiKey=$apikey";
    $header = 'Content-Type: application/json';
    if (!drush_shell_exec("wget -q -O - --no-check-certificate --timeout=20 --header=\"$header\" --post-data %s %s", $json, $url)) {
      if (!drush_shell_exec("curl -s --connect-timeout 20 --header \"$header\" --data %s %s", $json, $url)) {
        drush_log(dt('Drush usage statistics failed to post.'), 'debug');
        return FALSE;
      }
    }
    drush_log(dt('Drush usage statistics successfully posted.'), 'debug');
    // Empty the usage.txt file.
    unlink($file);
    return TRUE;
  }
}
