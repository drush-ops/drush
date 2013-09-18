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
 * Log and/or send usage data to Segment.io.
 *
 * An organization can implement own hook_drush_exit() to send data to a
 * different endpoint.
 */
function usage_drush_exit() {
  // Ignore statistics for simulated commands.
  if (!drush_get_context('DRUSH_SIMULATE')) {
    $file = _drush_usage_get_file();
    if (drush_get_option('drush_usage_log', FALSE)) {
      _drush_usage_log(drush_get_command(), $file);
    }
    $min_size_to_send = drush_get_option('drush_usage_size', 51200);
    $current_size = filesize($file);
    if (drush_get_option('drush_usage_send', FALSE) && $current_size > $min_size_to_send) {
      $cmd = "python " . DRUSH_BASE_PATH . "/vendor/segmentio/analytics-php/file_reader.py --file ${file}";
      drush_shell_exec($cmd);

      $output = preg_grep('/ImportError/', drush_shell_exec_output());
      if (empty($output)) {
        drush_log(dt('Sent usage stats (!bytesize bytes).', array('!bytesize' => $current_size)), 'ok');
      } else {
        return drush_set_error('DRUSH_USAGE_ANALYTICS_PY_PKG', dt('Python package analytics-python not installed. Cannot send usage stats. Please run `[sudo] pip install analytics-python`.'));
      }

    }
  }
}

/**
 * Implementation of hook_cron().
 */
function hook_cron() {
}

/**
 * Set option to send usage to Segment.io.
 *
 * See usage_drush_exit() for more information.
 */
function drush_usage_send() {
  $file = _drush_usage_get_file(TRUE);
  if ($file) {
    drush_set_option('drush_usage_send', TRUE);
    drush_set_option('drush_usage_size', 0);
    drush_print(dt('To automatically send anonymous usage data, add the following to a .drushrc.php file: $options[\'drush_usage_send\'] = TRUE;'));
  }
  else {
    return drush_set_error('DRUSH_NO_USAGE_FILE', dt('No usage file; set $options[\'drush_usage_log\'] = TRUE; in a .drushrc.php file to enable.'));
  }
}

/**
 * Displays usage file.
 */
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
  else {
    return drush_set_error('DRUSH_NO_USAGE_FILE', dt('No usage file; set $options[\'drush_usage_log\'] = TRUE; in a .drushrc.php file to enable.'));
  }
}

/**
 * Returns path to usage file.
 */
function _drush_usage_get_file($required = FALSE) {
  $file = drush_directory_cache('usage') . '/usage.txt';
  if (!file_exists($file) && $required) {
    return FALSE;

  }
  return $file;
}

function _drush_usage_log($command, $file) {
  drush_merge_engine_data($command);

  # Initialize the drush usage tracker.
  $drush_api_key = 'iizjgmy3n692hgiy0twe';
  Analytics::init(
    $drush_api_key,
    array(
      "consumer" => "file",
      "filename" => $file,
      "ssl" => true
    )
  );

  # Identify by anonymous drush install hash.
  $traits = array();
  Analytics::identify(
    _drush_usage_get_drush_key(),
    $traits
  );

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
  );

  # Track each event
  $action = "Ran ${command['command']} Command";
  $properties = array(
    'site_key' => _drush_usage_get_site_key(),
    'opt' => $command_specific,
    'major' => DRUSH_MAJOR_VERSION,
    'minor' => DRUSH_MINOR_VERSION,
    'os' => php_uname('s')
  );
  Analytics::track(
    _drush_usage_get_drush_key(),
    $action,
    $properties
  );
}

function _drush_usage_get_drush_key() {
  return sha1($_SERVER["USER"] . constant("DRUSH_BASE_PATH"));
}

function _drush_usage_get_site_key() {
  if (drush_bootstrap_max() >= DRUSH_BOOTSTRAP_DRUPAL_DATABASE) {
    global $base_url;
    $site_key = sha1($base_url . drupal_get_private_key());
  } else {
    $site_key = null;
  }

  return $site_key;
}
