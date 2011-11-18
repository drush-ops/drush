<?php

/**
 * @file Drush backend API
 *
 * When a drush command is called with the --backend option,
 * it will buffer all output, and instead return a JSON encoded
 * string containing all relevant information on the command that
 * was just executed.
 *
 * Through this mechanism, it is possible for Drush commands to
 * invoke each other.
 *
 * There are many cases where a command might wish to call another
 * command in its own process, to allow the calling command to
 * intercept and act on any errors that may occur in the script that
 * was called.
 *
 * A simple example is if there exists an 'update' command for running
 * update.php on a specific site. The original command might download
 * a newer version of a module for installation on a site, and then
 * run the update script in a separate process, so that in the case
 * of an error running a hook_update_n function, the module can revert
 * to a previously made database backup, and the previously installed code.
 *
 * By calling the script in a separate process, the calling script is insulated
 * from any error that occurs in the called script, to the level that if a
 * php code error occurs (ie: misformed file, missing parenthesis, whatever),
 * it is still able to reliably handle any problems that occur.
 *
 * This is nearly a RESTful API. @see http://en.wikipedia.org/wiki/REST
 *
 * Instead of :
 *   http://[server]/[apipath]/[command]?[arg1]=[value1],[arg2]=[value2]
 *
 * It will call :
 *  [apipath] [command] --[arg1]=[value1] --[arg2]=[value2] --backend
 *
 * [apipath] in this case will be the path to the drush.php file.
 * [command] is the command you would call, for instance 'status'.
 *
 * GET parameters will be passed as options to the script.
 * POST parameters will be passed to the script as a JSON encoded associative array over STDIN.
 *
 * Because of this standard interface, Drush commands can also be executed on
 * external servers through SSH pipes, simply by prepending, 'ssh username@server.com'
 * in front of the command.
 *
 * If the key-based ssh authentication has been set up between the servers,
 * this will just work.  By default, drush is configured to disallow password
 * authentication; if you would like to enter a password for every connection,
 * then in your drushrc.php file, set $options['ssh-options'] so that it does NOT
 * include '-o PasswordAuthentication=no'.  See examples/example.drushrc.php.
 *
 * The results from backend API calls can be fetched via a call to
 * drush_backend_get_result().
 */

/**
 * Identify the JSON encoded output from a command.
 */
define('DRUSH_BACKEND_OUTPUT_DELIMITER', 'DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END');

/**
 * Identify JSON encoded "packets" embedded inside of backend
 * output; used to send out-of-band information durring a backend
 * invoke call (currently only used for log and error messages).
 */
define('DRUSH_BACKEND_PACKET_PATTERN', "\0DRUSH_BACKEND:%s\0\n");

/**
 * The backend result is the original PHP data structure (usually an array)
 * used to generate the output for the current command.
 */
function drush_backend_set_result($value) {
  if (drush_get_context('DRUSH_BACKEND')) {
    drush_set_context('BACKEND_RESULT', $value);
  }
}

/**
 * Retrieves the results from the last call to backend_invoke.
 *
 * @returns array
 *   An associative array containing information from the last
 *   backend invoke.  The keys in the array include:
 *
 *     - output: This item contains the textual output of
 *       the command that was executed.
 *     - object: Contains the PHP object representation of the
 *       result of the command.
 *     - self: The self object contains the alias record that was
 *       used to select the bootstrapped site when the command was
 *       executed.
 *     - error_status: This item returns the error status for the
 *       command.  Zero means "no error".
 *     - log: The log item contains an array of log messages from
 *       the command execution ordered chronologically.  Each log
 *       entery is an associative array.  A log entry contains
 *       following items:
 *         o  type: The type of log entry, such as 'notice' or 'warning'
 *         o  message: The log message
 *         o  timestamp: The time that the message was logged
 *         o  memory: Available memory at the time that the message was logged
 *         o  error: The error code associated with the log message
 *            (only for log entries whose type is 'error')
 *     - error_log: The error_log item contains another representation
 *       of entries from the log.  Only log entries whose 'error' item
 *       is set will appear in the error log.  The error log is an
 *       associative array whose key is the error code, and whose value
 *       is an array of messages--one message for every log entry with
 *       the same error code.
 *     - context: The context item contains a representation of all option
 *       values that affected the operation of the command, including both
 *       the command line options, options set in a drushrc.php configuration
 *       files, and options set from the alias record used with the command.
 */
function drush_backend_get_result() {
  return drush_get_context('BACKEND_RESULT');
}

/**
 * Print the json-encoded output of this command, including the
 * encoded log records, context information, etc.
 */
function drush_backend_output() {
  $data = array();

  if (drush_get_context('DRUSH_PIPE')) {
    $pipe = drush_get_context('DRUSH_PIPE_BUFFER');
    $data['output'] = $pipe; // print_r($pipe, TRUE);
  }
  else {
    // Strip out backend commands.
    $packet_regex = strtr(sprintf(DRUSH_BACKEND_PACKET_PATTERN, "([^\0]*)"), array("\0" => "\\0"));
    $data['output'] = preg_replace("/$packet_regex/s", '', drush_backend_output_collect(NULL));
  }

  if (drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_end_clean();
  }

  $result_object = drush_backend_get_result();
  if (isset($result_object)) {
    $data['object'] = $result_object;
  }

  $error = drush_get_error();
  $data['error_status'] = ($error) ? $error : DRUSH_SUCCESS;

  $data['log'] = drush_get_log(); // Append logging information
  // The error log is a more specific version of the log, and may be used by calling
  // scripts to check for specific errors that have occurred.
  $data['error_log'] = drush_get_error_log();
  // If there is a @self record, then include it in the result
  $self_record = drush_sitealias_get_record('@self');
  if (!empty($self_record)) {
    $site_context = drush_get_context('site', array());
    unset($site_context['config-file']);
    unset($site_context['context-path']);
    unset($self_record['loaded-config']);
    unset($self_record['#name']);
    $data['self'] = array_merge($site_context, $self_record);
  }

  // Return the options that were set at the end of the process.
  $data['context']  = drush_get_merged_options();
  printf(DRUSH_BACKEND_OUTPUT_DELIMITER, json_encode($data));
}

/**
 * Callback to collect backend command output.
 */
function drush_backend_output_collect($string) {
  static $output = '';
  if (is_null($string)) {
    return $output;
  }

  $output .= $string;
  return $string;
}

/**
 * Output buffer functions that discards all output but backend packets.
 */
function drush_backend_output_discard($string) {
  $packet_regex = strtr(sprintf(DRUSH_BACKEND_PACKET_PATTERN, "([^\0]*)"), array("\0" => "\\0"));
  if (preg_match_all("/$packet_regex/s", $string, $matches)) {
    return implode('', $matches[0]);
  }
}

/**
 * Output a backend packet if we're running as backend.
 *
 * @param packet
 *   The packet to send.
 * @param data
 *   Data for the command.
 *
 * @return
 *  A boolean indicating whether the command was output.
 */
function drush_backend_packet($packet, $data) {
  if (drush_get_context('DRUSH_BACKEND')) {
    $data['packet'] = $packet;
    $data = json_encode($data);
    drush_print(sprintf(DRUSH_BACKEND_PACKET_PATTERN, $data), 0, STDERR);
    return TRUE;
  }

  return FALSE;
}

/**
 * Parse output returned from a Drush command.
 *
 * @param string
 *    The output of a drush command
 * @param integrate
 *    Integrate the errors and log messages from the command into the current process.
 * @param outputted
 *    Whether output has already been handled.
 *
 * @return
 *   An associative array containing the data from the external command, or the string parameter if it
 *   could not be parsed successfully.
 */
function drush_backend_parse_output($string, $backend_options = array(), $outputted = FALSE) {
  $regex = sprintf(DRUSH_BACKEND_OUTPUT_DELIMITER, '(.*)');

  preg_match("/$regex/s", $string, $match);

  if (!empty($match) && $match[1]) {
    // we have our JSON encoded string
    $output = $match[1];
    // remove the match we just made and any non printing characters
    $string = trim(str_replace(sprintf(DRUSH_BACKEND_OUTPUT_DELIMITER, $match[1]), '', $string));
  }

  if (!empty($output)) {
    $data = json_decode($output, TRUE);
    if (is_array($data)) {
      _drush_backend_integrate($data, $backend_options, $outputted);
      return $data;
    }
  }
  return $string;
}

/**
 * Integrate log messages and error statuses into the current
 * process.
 *
 * Output produced by the called script will be printed if we didn't print it
 * on the fly, errors will be set, and log messages will be logged locally, if
 * not already logged.
 *
 * @param data
 *    The associative array returned from the external command.
 * @param outputted
 *    Whether output has already been handled.
 */
function _drush_backend_integrate($data, $backend_options, $outputted) {
  if (is_array($data['log']) && $backend_options['log'] && !$outputted) {
    foreach($data['log'] as $log) {
      $message = is_array($log['message']) ? implode("\n", $log['message']) : $log['message'];
      if (isset($backend_options['output-label'])) {
        $message = $backend_options['output-label'] . $message;
      }
      if (!is_null($log['error']) && $backend_options['integrate']) {
        drush_set_error($log['error'], $message);
      }
      else {
        drush_log($message, $log['type']);
      }
    }
  }
  // Output will either be printed, or buffered to the drush_backend_output command.
  // If the output has already been printed, then we do not need to show it again on a failure.
  if (!$outputted) {
    if (drush_cmp_error('DRUSH_APPLICATION_ERROR') && !empty($data['output'])) {
      drush_set_error("DRUSH_APPLICATION_ERROR", dt("Output from failed command :\n !output", array('!output' => $data['output'])));
    }
    elseif ($backend_options['output']) {
      _drush_backend_print_output($data['output'], $backend_options);
    }
  }
}

/**
 * Call an external command using proc_open.
 *
 * @param cmd
 *    The command to execute. This command already needs to be properly escaped.
 * @param data
 *    An associative array that will be JSON encoded and passed to the script being called.
 *    Objects are not allowed, as they do not json_decode gracefully.
 *
 * @return
 *   False if the command could not be executed, or did not return any output.
 *   If it executed successfully, it returns an associative array containing the command
 *   called, the output of the command, and the error code of the command.
 */
function _drush_proc_open($cmd, $post_options = NULL, $context = NULL, $backend_options = array()) {

  $descriptorspec = array(
     0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
     1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
     2 => array("pipe", "w")   // stderr is a pipe the child will write to
  );
  $process = proc_open($cmd, $descriptorspec, $pipes, null, null, array('context' => $context));
  if (is_resource($process)) {
    if ($post_options) {
      fwrite($pipes[0], json_encode($post_options)); // pass the data array in a JSON encoded string
    }
    // If we do not close stdin here, then we cause a deadlock;
    // see: http://drupal.org/node/766080#comment-4309936
    // If we reimplement interactive commands to also use
    // _drush_proc_open, then clearly we would need to keep
    // this open longer.
    fclose($pipes[0]);

    $info = stream_get_meta_data($pipes[1]);
    stream_set_blocking($pipes[1], TRUE);
    stream_set_timeout($pipes[1], 1);
    $output = '';
    $end_of_output = FALSE;
    $outputted = FALSE;
    while (!feof($pipes[1]) && !$info['timed_out']) {
      $string = fread($pipes[1], 4096);
      $output_end_pos = strpos($string, 'DRUSH_BACKEND_OUTPUT_START>>>');
      if ($output_end_pos !== FALSE) {
        $trailing_string = substr($string, 0, $output_end_pos);
        drush_backend_parse_packets($trailing_string, $backend_options);
        _drush_backend_print_output($trailing_string, $backend_options);
        $end_of_output = TRUE;
      }
      if (!$end_of_output) {
        drush_backend_parse_packets($string, $backend_options);
        // Pass output through.
        _drush_backend_print_output($string, $backend_options);
        $outputted = TRUE;
      }
      $output .= $string;
      $info = stream_get_meta_data($pipes[1]);
      flush();
    };

    $info = stream_get_meta_data($pipes[2]);
    stream_set_blocking($pipes[2], TRUE);
    stream_set_timeout($pipes[2], 1);
    while (!feof($pipes[2]) && !$info['timed_out']) {
      $string = fgets($pipes[2], 4096);
      $output .= $string;
      fwrite(STDERR, $string);
      $info = stream_get_meta_data($pipes[2]);
      flush();
    };

    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return array('cmd' => $cmd, 'output' => $output, 'code' => $code, 'outputted' => $outputted);
  }
  return FALSE;
}

/**
 * Print the output received from a call to backend invoke,
 * adding the label to the head of each line if necessary.
 */
function _drush_backend_print_output($output_string, $backend_options) {
  if ($backend_options['output'] && !empty($output_string)) {
    $output_label = array_key_exists('output-label', $backend_options) ? $backend_options['output-label'] : FALSE;
    if ($output_label) {
      // Remove one, and only one newline from the end of the
      // string. Else we'll get an extra 'empty' line.
      foreach (explode("\n", preg_replace('/\\n$/', '', $output_string)) as $line) {
        fwrite(STDOUT, $output_label . rtrim($line) . "\n");
      }
    }
    else {
      fwrite(STDOUT, $output_string);
    }
  }
}

/**
 * Parse out and remove backend packet from the supplied string and
 * invoke the commands.
 */
function drush_backend_parse_packets(&$string, $backend_options) {
  $packet_regex = strtr(sprintf(DRUSH_BACKEND_PACKET_PATTERN, "([^\0]*)"), array("\0" => "\\0"));
  if (preg_match_all("/$packet_regex/s", $string, $match, PREG_PATTERN_ORDER)) {
    foreach ($match[1] as $packet_data) {
      $entry = (array) json_decode($packet_data);
      if (is_array($entry) && isset($entry['packet'])) {
        $function = 'drush_backend_packet_' . $entry['packet'];
        if (function_exists($function)) {
          $function($entry, $backend_options);
        }
        else {
          drush_log(dt("Unknown backend packet @packet", array('@packet' => $entry['packet'])), 'notice');
        }
      }
      else {
        drush_log(dt("Malformed backend packet"), 'error');
        drush_log(dt("Bad packet: @packet", array('@packet' => print_r($entry, TRUE))), 'debug');
        drush_log(dt("String is: @str", array('@str' => $packet_data), 'debug'));
      }
    }

    $string = trim(preg_replace("/$packet_regex/s", '', $string));
  }
}

/**
 * Backend command for setting errors.
 */
function drush_backend_packet_set_error($data, $backend_options) {
  if (!$backend_options['integrate']) {
    return;
  }
  $output_label = "";
  if (array_key_exists('output-label', $backend_options)) {
    $output_label = $backend_options['output-label'];
  }
  drush_set_error($data['error'], $data['message'], $output_label);
}

/**
 * Default options for backend_invoke commands.
 */
function _drush_backend_adjust_options($site_record, $command, $command_options, $backend_options) {
  // By default, if the caller does not specify a value for 'output', but does
  // specify 'integrate' === FALSE, then we will set output to FALSE.  Otherwise we
  // will allow it to default to TRUE.
  if ((array_key_exists('integrate', $backend_options)) && ($backend_options['integrate'] === FALSE) && (!array_key_exists('output', $backend_options))) {
    $backend_options['output'] = FALSE;
  }
  $result = $backend_options + array(
     'method' => 'GET',
     'output' => TRUE,
     'log' => TRUE,
     'integrate' => TRUE,
     'backend' => TRUE,
  );
  // Convert '#integrate' et. al. into backend options
  foreach ($command_options as $key => $value) {
    if (substr($key,0,1) != '#') {
      $result[substr($key,1)] = $value;
    }
  }
  return $result;
}

/**
 * Execute a new local or remote command in a new process.
 *
 * n.b. Prefer drush_invoke_process() to this function.
 *
 * @param site_record
 *   An array containing information used to generate the command.
 *   'remote-host'
 *      Optional. A remote host to execute the drush command on.
 *   'remote-user'
 *      Optional. Defaults to the current user. If you specify this, you can choose which module to send.
 *   'ssh-options'
 *      Optional.  Defaults to "-o PasswordAuthentication=no"
 *   'path-aliases'
 *      Optional; contains paths to folders and executables useful to the command.
 *      '%drush-script'
 *        Optional. Defaults to the current drush.php file on the local machine, and
 *        to simply 'drush' (the drush script in the current PATH) on remote servers.
 *        You may also specify a different drush.php script explicitly.  You will need
 *        to set this when calling drush on a remote server if 'drush' is not in the
 *        PATH on that machine.
 * @param command
 *    A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
 * @param args
 *    An array of arguments for the command.
 * @param command_options
 *    Optional. An array containing options to pass to the remote script.
 *    Array items with a numeric key are treated as optional arguments to the
 *    command.  This parameter is a reference, as any options that have been
 *    represented as either an option, or an argument will be removed.  This
 *    allows you to pass the left over options as a JSON encoded string,
 *    without duplicating data.
 * @param backend_options
 *    Optional. An array of options for the invocation.
 *     'method'
 *        Optional. Defaults to 'GET'.
 *        If this parameter is set to 'POST', the $data array will be passed
 *        to the script being called as a JSON encoded string over the STDIN
 *        pipe of that process. This is preferable if you have to pass
 *        sensitive data such as passwords and the like.
 *        For any other value, the $data array will be collapsed down into a
 *        set of command line options to the script.
 *     'integrate'
 *        Optional. Defaults to TRUE.
 *        If TRUE, any error statuses will be integrated into the current
 *        process. This might not be what you want, if you are writing a
 *        command that operates on multiple sites.
 *     'log'
 *        Optional. Defaults to TRUE.
 *        If TRUE, any log messages will be integrated into the current
 *        process.
 *     'output'
 *        Optional. Defaults to TRUE.
 *        If TRUE, output from the command will be synchronously printed to
 *        stdout.
 *     'drush-script'
 *        Optional. Defaults to the current drush.php file on the local
 *        machine, and to simply 'drush' (the drush script in the current
 *        PATH) on remote servers.  You may also specify a different drush.php
 *        script explicitly.  You will need to set this when calling drush on
 *        a remote server if 'drush' is not in the PATH on that machine.
 *
 * @return
 *   If the command could not be completed successfully, FALSE.
 *   If the command was completed, this will return an associative array containing the data from drush_backend_output().
 */
function drush_backend_invoke_sitealias_command($site_record, $command, $args, $command_options = array(), $backend_options = array()) {
  $backend_options = _drush_backend_adjust_options($site_record, $command, $command_options, $backend_options);
  $os = drush_os($site_record);

  $site_record += array( 'path-aliases' => array() );
  $site_record['path-aliases'] += array(
     '%drush-script' => NULL,
  );
  $drush_path = $site_record['path-aliases']['%drush-script'];

  // If the caller did not pass in a specific path to drush, then we will
  // use a default value.  For commands that are being executed on the same
  // machine, we will use DRUSH_COMMAND, which is the path to the drush.php
  // that is running right now.  For remote commands, we will run a wrapper
  // script instead of drush.php -- drush.bat on Windows, or drush on Linux.
  $drush_command_path = drush_build_drush_command($drush_path, array_key_exists('php', $command_options) ? $command_options['php'] : NULL, drush_os($site_record), array_key_exists('remote-host', $site_record));

  // Insure that contexts such as DRUSH_SIMULATE and NO_COLOR are included.
  $command_options += _drush_backend_get_global_contexts($site_record);

  list($post_options, $commandline_options, $drush_global_options) = _drush_backend_classify_options($command_options, $backend_options);
  $cmd = _drush_backend_generate_command($site_record, $drush_command_path . " " . _drush_backend_argument_string($drush_global_options, $os) . " " . $command, $args, $commandline_options, $backend_options) . ' 2>&1';
  return _drush_backend_invoke($cmd, $post_options, $backend_options);
}

/**
 * Find all of the drush contexts that are used to cache global values and
 * return them in an associative array.
 */
function _drush_backend_get_global_contexts($site_record) {
  $result = array();
  $global_option_list = drush_get_global_options(FALSE);
  foreach ($global_option_list as $global_key => $global_metadata) {
    if (is_array($global_metadata)) {
      $value = '';
      if (!array_key_exists('never-propagate', $global_metadata)) {
	if ((array_key_exists('propagate-cli-value', $global_metadata))) {
          $value = drush_get_option($global_key, '', 'cli');
	}
	elseif ((array_key_exists('context', $global_metadata))) {
          // If the context is declared to be a 'local-context-only',
          // then only put it in if this is a local dispatch.
          if (!array_key_exists('local-context-only', $global_metadata) || !array_key_exists('remot-host', $site_record)) {
            $value = drush_get_context($global_metadata['context'], array());
          }
	}
	if (!empty($value)) {
          $result[$global_key] = $value;
	}
      }
    }
  }
  return $result;
}

/**
 * Take all of the values in the $command_options array, and place each of
 * them into one of the following result arrays:
 *
 *     - $post_options: options to be encoded as JSON and written to the
 *       standard input of the drush subprocess being executed.
 *     - $commandline_options: options to be placed on the command line of the drush
 *       subprocess.
 *     - $drush_global_options: the drush global options also go on the command
 *       line, but appear before the drush command name rather than after it.
 */
function _drush_backend_classify_options($command_options, $backend_options) {
  // In 'POST' mode (the default, remove everything (except the items marked 'never-post'
  // in the global option list) from the commandline options and put them into the post options.  
  // The post options will be json-encoded and sent to the command via stdin
  $global_option_list = drush_get_global_options(FALSE); // These should be in the command line.
  $method_post = ((!array_key_exists('method', $backend_options)) || ($backend_options['method'] == 'POST'));
  $post_options = array();
  $commandline_options = array();
  $drush_global_options = array();
  foreach ($command_options as $key => $value) {
    $global = array_key_exists($key, $global_option_list);
    $propagate = !is_array($value);
    $special = FALSE;
    if ($global) {
      $propagate = (!array_key_exists('never-propagate', $global_option_list[$key]));
      $special = (array_key_exists('never-post', $global_option_list[$key]));
      if ($propagate) {
        // We will allow 'merge-pathlist' contexts to be propogated.  Right now
        // these are all 'local-context-only' options; if we allowed them to
        // propogate remotely, then we would need to get the right path separator
        // for the remote machine.
        if (is_array($value) && array_key_exists('merge-pathlist', $global_option_list[$key])) {
          $value = implode(PATH_SEPARATOR, $value);
        }
      }
    }
    // Just remove options that are designated as non-propagating
    if ($propagate === TRUE) {
      // In METHOD POST, move command options to post options
      if ($method_post && ($special === FALSE)) {
        $post_options[$key] = $value;
      }
      // In METHOD GET, separate out drush global options
      elseif ($global) {
        $drush_global_options[$key] = $value;
      }
      else {
        $commandline_options[$key] = $value;
      }
    }
  }
  return array($post_options, $commandline_options, $drush_global_options);
}

/**
 * Create a new pipe with proc_open, and attempt to parse the output.
 *
 * We use proc_open instead of exec or others because proc_open is best
 * for doing bi-directional pipes, and we need to pass data over STDIN
 * to the remote script.
 *
 * Exec also seems to exhibit some strangeness in keeping the returned
 * data intact, in that it modifies the newline characters.
 *
 * @param cmd
 *   The complete command line call to use.
 * @param post_options
 *   An associative array to json-encode and pass to the remote script on stdin.
 * @param backend_options
 *   Options for the invocation.
 *
 * @return
 *   If the command could not be completed successfully, FALSE.
 *   If the command was completed, this will return an associative array containing the data from drush_backend_output().
 */
function _drush_backend_invoke($cmd, $post_options = NULL, $backend_options = array()) {
  if (drush_get_context('DRUSH_SIMULATE') && !array_key_exists('override-simulated', $backend_options)) {
    drush_print(dt('Simulating backend invoke: !cmd', array('!cmd' => $cmd)));
    return FALSE;
  }
  drush_log(dt('Backend invoke: !cmd', array('!cmd' => $cmd)), 'command');
  if (array_key_exists('interactive', $backend_options)) {
    drush_log(dt("executing !cmd", array('!cmd' => $cmd)));
    return drush_shell_proc_open($cmd);
  }
  else {
    $proc = _drush_proc_open($cmd, $post_options, NULL, $backend_options);

    if (($proc['code'] == DRUSH_APPLICATION_ERROR) && $backend_options['integrate']) {
      drush_set_error('DRUSH_APPLICATION_ERROR', dt("The external command could not be executed due to an application error."));
    }

    if ($proc['output']) {
      $values = drush_backend_parse_output($proc['output'], $backend_options, $proc['outputted']);
      if (is_array($values)) {
        return $values;
      }
      else {
        return drush_set_error('DRUSH_FRAMEWORK_ERROR', dt("The command could not be executed successfully (returned: !return, code: %code)", array("!return" => $proc['output'], "%code" =>  $proc['code'])));
      }
    }
  }
  return FALSE;
}

/**
 * Helper function that generates an anonymous site alias specification for
 * the given parameters.
 */
function drush_backend_generate_sitealias($backend_options) {
  // Ensure default values.
  $backend_options += array(
    'remote-host' => NULL,
    'remote-user' => NULL,
    'ssh-options' => NULL,
    'drush-script' => NULL,
  );
  return array(
    'remote-host' => $backend_options['remote-host'],
    'remote-user' => $backend_options['remote-user'],
    'ssh-options' => $backend_options['ssh-options'],
    'path-aliases' => array(
      '%drush-script' => $backend_options['drush-script'],
    ),
  );
}

/**
 * Generate a command to execute.
 *
 * @param site_record
 *   An array containing information used to generate the command.
 *   'remote-host'
 *      Optional. A remote host to execute the drush command on.
 *   'remote-user'
 *      Optional. Defaults to the current user. If you specify this, you can choose which module to send.
 *   'ssh-options'
 *      Optional.  Defaults to "-o PasswordAuthentication=no"
 *   'path-aliases'
 *      Optional; contains paths to folders and executables useful to the command.
 *      '%drush-script'
 *        Optional. Defaults to the current drush.php file on the local machine, and
 *        to simply 'drush' (the drush script in the current PATH) on remote servers.
 *        You may also specify a different drush.php script explicitly.  You will need
 *        to set this when calling drush on a remote server if 'drush' is not in the
 *        PATH on that machine.
 * @param command
 *    A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
 * @param args
 *    An array of arguments for the command.
 * @param command_options
 *    Optional. An array containing options to pass to the remote script.
 *    Array items with a numeric key are treated as optional arguments to the
 *    command.  This parameter is a reference, as any options that have been
 *    represented as either an option, or an argument will be removed.  This
 *    allows you to pass the left over options as a JSON encoded string,
 *    without duplicating data.
 * @param backend_options
 *    Optional. An array of options for the invocation.
 *    @see drush_backend_invoke for documentation.
 *
 * @return
 *   A text string representing a fully escaped command.
 */
function _drush_backend_generate_command($site_record, $command, $args = array(), $command_options = array(), $backend_options = array()) {
  $drush_path = null;
  $php = '';

  $site_record += array(
    'remote-host' => NULL,
    'remote-user' => NULL,
    'ssh-options' => NULL,
    'path-aliases' => array(),
  );

  $hostname = $site_record['remote-host'];
  $username = $site_record['remote-user'];
  $ssh_options = $site_record['ssh-options'];
  $os = drush_os($site_record);

  if (drush_is_local_host($hostname)) {
    $hostname = null;
  }

  foreach ($command_options as $key => $arg) {
    if (is_numeric($key)) {
      $args[] = $arg;
      unset($command_options[$key]);
    }
  }
  
  $cmd[] = $command;
  foreach ($args as $arg) {
    $cmd[] = drush_escapeshellarg($arg, $os);
  }
  $option_str = _drush_backend_argument_string($command_options, $os);
  if (!empty($option_str)) {
    $cmd[] = " " . $option_str;
  }
  if (!empty($backend_options['backend']) && empty($backend_options['interactive'])) {
    $cmd[] = "--backend=2";
  }
  if (!empty($backend_options['interactive'])) {
    $cmd[] .= '--invoke';
  }
  $command = implode(' ', array_filter($cmd, 'strlen'));
  if (!is_null($hostname)) {
    if (drush_is_windows($os)) {
      if (!is_null($username) && (array_key_exists('winrs-password', $site_record))) {
        $username = " -u:" . drush_escapeshellarg($username, "LOCAL") . " -p:" . drush_escapeshellarg($site_record['winrs-password'], "LOCAL");
      }
      $command = "winrs" . $username . " -r:" . drush_escapeshellarg($hostname, "LOCAL") . " " . drush_escapeshellarg($command, "LOCAL");
    }
    else {
      $username = (!is_null($username)) ? drush_escapeshellarg($username, "LOCAL") . "@" : '';
      $ssh_options = $site_record['ssh-options'];
      $ssh_options = (!is_null($ssh_options)) ? $ssh_options : drush_get_option('ssh-options', "-o PasswordAuthentication=no");

      $ssh_cmd[] = "ssh";
      $ssh_cmd[] = $ssh_options;
      $ssh_cmd[] = $username . drush_escapeshellarg($hostname, "LOCAL");
      $ssh_cmd[] = drush_escapeshellarg($command . ' 2>&1', "LOCAL");

      // Remove NULLs and separate with spaces
      $command = implode(' ', array_filter($ssh_cmd, 'strlen'));
    }
  }
  else {
    // TODO: `tty` is not usable on Windows.  Is this necessary at all, and if so, is there a better way to do it?
    if (!drush_is_windows() && !empty($backend_options['interactive'])) {
      $command .= ' > `tty`';
    }
  }

  return $command;
}

/**
 * Map the options to a string containing all the possible arguments and options.
 *
 * @param data
 *    Optional. An array containing options to pass to the remote script.
 *    Array items with a numeric key are treated as optional arguments to the command.
 *    This parameter is a reference, as any options that have been represented as either an option, or an argument will be removed.
 *    This allows you to pass the left over options as a JSON encoded string, without duplicating data.
 * @param method
 *    Optional. Defaults to 'GET'.
 *    If this parameter is set to 'POST', the $data array will be passed to the script being called as a JSON encoded string over
 *    the STDIN pipe of that process. This is preferable if you have to pass sensitive data such as passwords and the like.
 *    For any other value, the $data array will be collapsed down into a set of command line options to the script.
 * @return
 *    A properly formatted and escaped set of arguments and options to append to the drush.php shell command.
 */
function _drush_backend_argument_string($data, $os = NULL) {
  $options = array();

  foreach ($data as $key => $value) {
    if (!is_array($value) && !is_object($value) && !is_null($value)) {
      if (substr($key,0,1) != '#') {
        $options[$key] = $value;
      }
    }
  }

  $option_str = '';
  foreach ($options as $key => $value) {
    $option_str .= _drush_escape_option($key, $value, $os);
  }

  return $option_str;
}

/**
 * Return a properly formatted and escaped command line option
 *
 * @param key
 *   The name of the option.
 * @param value
 *   The value of the option.
 *
 * @return
 *   If the value is set to TRUE, this function will return " --key"
 *   In other cases it will return " --key='value'"
 */
function _drush_escape_option($key, $value = TRUE, $os = NULL) {
  if ($value !== TRUE) {
    $option_str = " --$key=" . drush_escapeshellarg($value, $os);
  }
  else {
    $option_str = " --$key";
  }
  return $option_str;
}

/**
 * Read options fron STDIN during POST requests.
 *
 * This function will read any text from the STDIN pipe,
 * and attempts to generate an associative array if valid
 * JSON was received.
 *
 * @return
 *   An associative array of options, if successfull. Otherwise FALSE.
 */
function _drush_backend_get_stdin() {
  $fp = fopen('php://stdin', 'r');
  // Windows workaround: we cannot count on stream_get_contents to
  // return if STDIN is reading from the keyboard.  We will therefore
  // check to see if there are already characters waiting on the
  // stream (as there always should be, if this is a backend call),
  // and if there are not, then we will exit.
  // This code prevents drush from hanging forever when called with
  // --backend from the commandline; however, overall it is still
  // a futile effort, as it does not seem that backend invoke can
  // successfully write data to that this function can read,
  // so the argument list and command always come out empty. :(
  // Perhaps stream_get_contents is the problem, and we should use
  // the technique described here:
  //   http://bugs.php.net/bug.php?id=30154
  // n.b. the code in that issue passes '0' for the timeout in stream_select
  // in a loop, which is not recommended.
  // Note that the following DOES work:
  //   drush ev 'print(json_encode(array("test" => "XYZZY")));' | drush status --backend
  // So, redirecting input is okay, it is just the proc_open that is a problem.
  if (drush_is_windows()) {
    // Note that stream_select uses reference parameters, so we need variables (can't pass a constant NULL)
    $read = array($fp);
    $write = NULL;
    $except = NULL;
    // Question: might we need to wait a bit for STDIN to be ready,
    // even if the process that called us immediately writes our parameters?
    // Passing '100' for the timeout here causes us to hang indefinitely
    // when called from the shell.
    $changed_streams = stream_select($read, $write, $except, 0);
    // Return on error (FALSE) or no changed streams (0).
    // Oh, according to http://php.net/manual/en/function.stream-select.php,
    // stream_select will return FALSE for streams returned by proc_open.
    // That is not applicable to us, is it? Our stream is connected to a stream
    // created by proc_open, but is not a stream returned by proc_open.
    if ($changed_streams < 1) {
      return FALSE;
    }
  }
  stream_set_blocking($fp, FALSE);
  $string = stream_get_contents($fp);
  fclose($fp);
  if (trim($string)) {
    return json_decode($string, TRUE);
  }
  return FALSE;
}
