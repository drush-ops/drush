<?php

/**
 * @file
 * Drush backend API
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

use Drush\Log\LogLevel;
use Drush\Preflight\PreflightArgs;

/**
 * Identify the JSON encoded output from a command.
 *
 * Note that Drush now outputs a null ("\0") before the DRUSH_BACKEND_OUTPUT_DELIMITER,
 * but this null occurs where this constant is output rather than being
 * included in the define.  This is done to maintain compatibility with
 * older versions of Drush, so that Drush-7.x can correctly parse backend messages
 * from calls made to Drush-5.x and earlier.  The null is removed via trim().
 */
define('DRUSH_BACKEND_OUTPUT_START', 'DRUSH_BACKEND_OUTPUT_START>>>');
define('DRUSH_BACKEND_OUTPUT_DELIMITER', DRUSH_BACKEND_OUTPUT_START . '%s<<<DRUSH_BACKEND_OUTPUT_END');

/**
 * Identify JSON encoded "packets" embedded inside of backend
 * output; used to send out-of-band information durring a backend
 * invoke call (currently only used for log and error messages).
 */
define('DRUSH_BACKEND_PACKET_START', "DRUSH_BACKEND:");
define('DRUSH_BACKEND_PACKET_PATTERN', "\0" . DRUSH_BACKEND_PACKET_START . "%s\n\0");

/**
 * The backend result is the original PHP data structure (usually an array)
 * used to generate the output for the current command.
 */
function drush_backend_set_result($value) {
  if (\Drush\Drush::backend()) {
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
 *       entry is an associative array.  A log entry contains
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
  $data = [];

  // Strip out backend commands.
  $packet_regex = strtr(sprintf(DRUSH_BACKEND_PACKET_PATTERN, "([^\0]*)"), ["\0" => "\\0"]);
  $packet_regex = str_replace("\n", "", $packet_regex);
  $data['output'] = preg_replace("/$packet_regex/s", '', drush_backend_output_collect(NULL));

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
    $site_context = drush_get_context('site', []);
    unset($site_context['config-file']);
    unset($site_context['context-path']);
    unset($self_record['loaded-config']);
    unset($self_record['#name']);
    $data['self'] = array_merge($site_context, $self_record);
  }

  // Return the options that were set at the end of the process.
  $data['context']  = drush_get_merged_options();
  printf("\0" . DRUSH_BACKEND_OUTPUT_DELIMITER, json_encode($data));
}

/**
 * Callback to collect backend command output.
 */
function drush_backend_output_collect($string) {
  static $output = '';
  if (!isset($string)) {
    return $output;
  }

  $output .= $string;
  return $string;
}

/**
 * Output buffer functions that discards all output but backend packets.
 */
function drush_backend_output_discard($string) {
  $packet_regex = strtr(sprintf(DRUSH_BACKEND_PACKET_PATTERN, "([^\0]*)"), ["\0" => "\\0"]);
  $packet_regex = str_replace("\n", "", $packet_regex);
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
  if (\Drush\Drush::backend()) {
    $data['packet'] = $packet;
    $data = json_encode($data);
    // We use 'fwrite' instead of 'drush_print' here because
    // this backend packet is out-of-band data.
    fwrite(STDERR, sprintf(DRUSH_BACKEND_PACKET_PATTERN, $data));
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
function drush_backend_parse_output($string, $backend_options = [], $outputted = FALSE) {
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
  // In 'integrate' mode, logs and errors have already been handled
  // by drush_backend_packet (sender) drush_backend_parse_packets (receiver - us)
  // during incremental output.  We therefore do not need to call drush_set_error
  // or drush_log here.  The exception is if the sender is an older version of
  // Drush (version 4.x) that does not send backend packets, then we will
  // not have processed the log entries yet, and must print them here.
  $received_packets = drush_get_context('DRUSH_RECEIVED_BACKEND_PACKETS', FALSE);
  if (is_array($data['log']) && $backend_options['log'] && (!$received_packets)) {
    foreach($data['log'] as $log) {
      $message = is_array($log['message']) ? implode("\n", $log['message']) : $log['message'];
      if (isset($backend_options['#output-label'])) {
        $message = $backend_options['#output-label'] . $message;
      }
      if (isset($log['error']) && $backend_options['integrate']) {
        drush_set_error($log['error'], $message);
      }
      elseif ($backend_options['integrate']) {
        drush_log($message, $log['type']);
      }
    }
  }
  // Output will either be printed, or buffered to the drush_backend_output command.
  // If the output has already been printed, then we do not need to show it again on a failure.
  if (!$outputted) {
    if (drush_cmp_error('DRUSH_APPLICATION_ERROR') && !empty($data['output'])) {
      drush_set_error("DRUSH_APPLICATION_ERROR", dt("Output from failed command :\n !output", ['!output' => $data['output']]));
    }
    elseif ($backend_options['output']) {
      _drush_backend_print_output($data['output'], $backend_options);
    }
  }
}

/**
 * Supress log message output during backend integrate.
 */
function _drush_backend_integrate_log($entry) {
}

/**
 * Call an external command using proc_open.
 *
 * @param cmds
 *    An array of records containing the following elements:
 *      'cmd' - The command to execute, already properly escaped
 *      'post-options' - An associative array that will be JSON encoded
 *        and passed to the script being called. Objects are not allowed,
 *        as they do not json_decode gracefully.
 *      'backend-options' - Options that control the operation of the backend invoke
 *     - OR -
 *    An array of commands to execute. These commands already need to be properly escaped.
 *    In this case, post-options will default to empty, and a default output label will
 *    be generated.
 * @param data
 *    An associative array that will be JSON encoded and passed to the script being called.
 *    Objects are not allowed, as they do not json_decode gracefully.
 *
 * @return
 *   False if the command could not be executed, or did not return any output.
 *   If it executed successfully, it returns an associative array containing the command
 *   called, the output of the command, and the error code of the command.
 */
function _drush_backend_proc_open($cmds, $process_limit, $context = NULL) {
  $descriptorspec = [
    0 => ["pipe", "r"],  // stdin is a pipe that the child will read from
    1 => ["pipe", "w"],  // stdout is a pipe that the child will write to
  ];

  $open_processes = [];
  $bucket = [];
  $process_limit = max($process_limit, 1);
  $is_windows = drush_is_windows();
  // Loop through processes until they all close, having a nap as needed.
  $nap_time = 0;
  while (count($open_processes) || count($cmds)) {
    $nap_time++;
    if (count($cmds) && (count($open_processes) < $process_limit)) {
      // Pop the site and command (key / value) from the cmds array
      end($cmds);
      $cmd = current($cmds);
      $site = key($cmds);
      unset($cmds[$site]);

      if (is_array($cmd)) {
        $c = $cmd['cmd'];
        $post_options = $cmd['post-options'];
        $backend_options = $cmd['backend-options'];
      }
      else {
        $c = $cmd;
        $post_options = [];
        $backend_options = [];
      }
      $backend_options += [
        '#output-label' => '',
        '#process-read-size' => 4096,
      ];
      $process = [];
      drush_log($backend_options['#output-label'] . $c);
      $process['process'] = proc_open($c, $descriptorspec, $process['pipes'], null, null, ['context' => $context]);
      if (is_resource($process['process'])) {
        if ($post_options) {
          fwrite($process['pipes'][0], json_encode($post_options)); // pass the data array in a JSON encoded string
        }
        // If we do not close stdin here, then we cause a deadlock;
        // see: http://drupal.org/node/766080#comment-4309936
        // If we reimplement interactive commands to also use
        // _drush_proc_open, then clearly we would need to keep
        // this open longer.
        fclose($process['pipes'][0]);

        $process['info'] = stream_get_meta_data($process['pipes'][1]);
        stream_set_blocking($process['pipes'][1], FALSE);
        stream_set_timeout($process['pipes'][1], 1);
        $bucket[$site]['cmd'] = $c;
        $bucket[$site]['output'] = '';
        $bucket[$site]['remainder'] = '';
        $bucket[$site]['backend-options'] = $backend_options;
        $bucket[$site]['end_of_output'] = FALSE;
        $bucket[$site]['outputted'] = FALSE;
        $open_processes[$site] = $process;
      }
      // Reset the $nap_time variable as there might be output to process next
      // time around:
      $nap_time = 0;
    }
    // Set up to call stream_select(). See:
    // http://php.net/manual/en/function.stream-select.php
    // We can't use stream_select on Windows, because it doesn't work for
    // streams returned by proc_open.
    if (!$is_windows) {
      $ss_result = 0;
      $read_streams = [];
      $write_streams = [];
      $except_streams = [];
      foreach ($open_processes as $site => &$current_process) {
        if (isset($current_process['pipes'][1])) {
          $read_streams[] = $current_process['pipes'][1];
        }
      }
      // Wait up to 2s for data to become ready on one of the read streams.
      if (count($read_streams)) {
        $ss_result = stream_select($read_streams, $write_streams, $except_streams, 2);
        // If stream_select returns a error, then fallback to using $nap_time.
        if ($ss_result !== FALSE) {
          $nap_time = 0;
        }
      }
    }

    foreach ($open_processes as $site => &$current_process) {
      if (isset($current_process['pipes'][1])) {
        // Collect output from stdout
        $bucket[$site][1] = '';
        $info = stream_get_meta_data($current_process['pipes'][1]);

        if (!feof($current_process['pipes'][1]) && !$info['timed_out']) {
          $string = $bucket[$site]['remainder'] . fread($current_process['pipes'][1], $backend_options['#process-read-size']);
          $bucket[$site]['remainder'] = '';
          $output_end_pos = strpos($string, DRUSH_BACKEND_OUTPUT_START);
          if ($output_end_pos !== FALSE) {
            $trailing_string = substr($string, 0, $output_end_pos);
            $trailing_remainder = '';
            // If there is any data in the trailing string (characters prior
            // to the backend output start), then process any backend packets
            // embedded inside.
            if (strlen($trailing_string) > 0) {
              drush_backend_parse_packets($trailing_string, $trailing_remainder, $bucket[$site]['backend-options']);
            }
            // If there is any data remaining in the trailing string after
            // the backend packets are removed, then print it.
            if (strlen($trailing_string) > 0) {
              _drush_backend_print_output($trailing_string . $trailing_remainder, $bucket[$site]['backend-options']);
              $bucket[$site]['outputted'] = TRUE;
            }
            $bucket[$site]['end_of_output'] = TRUE;
          }
          if (!$bucket[$site]['end_of_output']) {
            drush_backend_parse_packets($string, $bucket[$site]['remainder'], $bucket[$site]['backend-options']);
            // Pass output through.
            _drush_backend_print_output($string, $bucket[$site]['backend-options']);
            if (strlen($string) > 0) {
              $bucket[$site]['outputted'] = TRUE;
            }
          }
          $bucket[$site][1] .= $string;
          $bucket[$site]['output'] .= $string;
          $info = stream_get_meta_data($current_process['pipes'][1]);
          flush();

          // Reset the $nap_time variable as there might be output to process
          // next time around:
          if (strlen($string) > 0) {
            $nap_time = 0;
          }
        }
        else {
          fclose($current_process['pipes'][1]);
          unset($current_process['pipes'][1]);
          // close the pipe , set a marker

          // Reset the $nap_time variable as there might be output to process
          // next time around:
          $nap_time = 0;
        }
      }
      else {
        // if both pipes are closed for the process, remove it from active loop and add a new process to open.
        $bucket[$site]['code'] = proc_close($current_process['process']);
        unset($open_processes[$site]);

        // Reset the $nap_time variable as there might be output to process next
        // time around:
        $nap_time = 0;
      }
    }

    // We should sleep for a bit if we need to, up to a maximum of 1/10 of a
    // second.
    if ($nap_time > 0) {
      usleep(max($nap_time * 500, 100000));
    }
  }
  return $bucket;
  // TODO: Handle bad proc handles
  //}
  //return FALSE;
}



/**
 * Print the output received from a call to backend invoke,
 * adding the label to the head of each line if necessary.
 */
function _drush_backend_print_output($output_string, $backend_options) {
  if ($backend_options['output'] && !empty($output_string)) {
    $output_label = array_key_exists('#output-label', $backend_options) ? $backend_options['#output-label'] : FALSE;
    if (!empty($output_label)) {
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
function drush_backend_parse_packets(&$string, &$remainder, $backend_options) {
  $remainder = '';
  $packet_regex = strtr(sprintf(DRUSH_BACKEND_PACKET_PATTERN, "([^\0]*)"), ["\0" => "\\0"]);
  $packet_regex = str_replace("\n", "", $packet_regex);
  if (preg_match_all("/$packet_regex/s", $string, $match, PREG_PATTERN_ORDER)) {
    drush_set_context('DRUSH_RECEIVED_BACKEND_PACKETS', TRUE);
    foreach ($match[1] as $packet_data) {
      $entry = (array) json_decode($packet_data);
      if (is_array($entry) && isset($entry['packet'])) {
        $function = 'drush_backend_packet_' . $entry['packet'];
        if (function_exists($function)) {
          $function($entry, $backend_options);
        }
        else {
          drush_log(dt("Unknown backend packet @packet", ['@packet' => $entry['packet']]), LogLevel::INFO);
        }
      }
      else {
        drush_log(dt("Malformed backend packet"), LogLevel::ERROR);
        drush_log(dt("Bad packet: @packet", ['@packet' => print_r($entry, TRUE)]), LogLevel::DEBUG);
        drush_log(dt("String is: @str", ['@str' => $packet_data]), LogLevel::DEBUG);
      }
    }
    $string = preg_replace("/$packet_regex/s", '', $string);
  }
  // Check to see if there is potentially a partial packet remaining.
  // We only care about the last null; if there are any nulls prior
  // to the last one, they would have been removed above if they were
  // valid drush packets.
  $embedded_null = strrpos($string, "\0");
  if ($embedded_null !== FALSE) {
    // We will consider everything after $embedded_null to be part of
    // the $remainder string if:
    //   - the embedded null is less than strlen(DRUSH_BACKEND_OUTPUT_START)
    //     from the end of $string (that is, there might be a truncated
    //     backend packet header, or the truncated backend output start
    //     after the null)
    //   OR
    //   - the embedded null is followed by DRUSH_BACKEND_PACKET_START
    //     (that is, the terminating null for that packet has not been
    //     read into our buffer yet)
    if (($embedded_null + strlen(DRUSH_BACKEND_OUTPUT_START) >= strlen($string)) || (substr($string, $embedded_null + 1, strlen(DRUSH_BACKEND_PACKET_START)) == DRUSH_BACKEND_PACKET_START)) {
      $remainder = substr($string, $embedded_null);
      $string = substr($string, 0, $embedded_null);
    }
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
  if (array_key_exists('#output-label', $backend_options)) {
    $output_label = $backend_options['#output-label'];
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
  $has_site_specification = array_key_exists('root', $site_record) || array_key_exists('uri', $site_record);
  $result = $backend_options + [
     'method' => 'GET',
     'output' => TRUE,
     'log' => TRUE,
     'integrate' => TRUE,
     'backend' => TRUE,
     'dispatch-using-alias' => !$has_site_specification,
    ];
  // Convert '#integrate' et. al. into backend options
  foreach ($command_options as $key => $value) {
    if (substr($key,0,1) === '#') {
      $result[substr($key,1)] = $value;
    }
  }
  return $result;
}

/**
 * Execute a new local or remote command in a new process.
 *
 * @deprecated as of Drush 9.4.0 and will be removed in Drush 10. Instead, use
 *   drush_invoke_process().
 *
 * @param invocations
 *   An array of command records to execute. Each record should contain:
 *     'site':
 *       An array containing information used to generate the command.
 *         'remote-host'
 *            Optional. A remote host to execute the drush command on.
 *         'remote-user'
 *            Optional. Defaults to the current user. If you specify this, you can choose which module to send.
 *         'ssh-options'
 *            Optional.  Defaults to "-o PasswordAuthentication=no"
 *         '#env-vars'
 *            Optional. An associative array of environmental variables to prefix the Drush command with.
 *         'path-aliases'
 *            Optional; contains paths to folders and executables useful to the command.
 *         '%drush-script'
 *            Optional. Defaults to the current drush.php file on the local machine, and
 *            to simply 'drush' (the drush script in the current PATH) on remote servers.
 *            You may also specify a different drush.php script explicitly.  You will need
 *            to set this when calling drush on a remote server if 'drush' is not in the
 *            PATH on that machine.
 *     'command':
 *       A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
 *     'args':
 *       An array of arguments for the command.
 *     'options'
 *       Optional. An array containing options to pass to the remote script.
 *       Array items with a numeric key are treated as optional arguments to the
 *       command.
 *     'backend-options':
 *       Optional. Additional parameters that control the operation of the invoke.
 *         'method'
 *            Optional. Defaults to 'GET'.
 *            If this parameter is set to 'POST', the $data array will be passed
 *            to the script being called as a JSON encoded string over the STDIN
 *            pipe of that process. This is preferable if you have to pass
 *            sensitive data such as passwords and the like.
 *            For any other value, the $data array will be collapsed down into a
 *            set of command line options to the script.
 *         'integrate'
 *            Optional. Defaults to TRUE.
 *            If TRUE, any error statuses will be integrated into the current
 *            process. This might not be what you want, if you are writing a
 *            command that operates on multiple sites.
 *         'log'
 *            Optional. Defaults to TRUE.
 *            If TRUE, any log messages will be integrated into the current
 *            process.
 *         'output'
 *            Optional. Defaults to TRUE.
 *            If TRUE, output from the command will be synchronously printed to
 *            stdout.
 *         'drush-script'
 *            Optional. Defaults to the current drush.php file on the local
 *            machine, and to simply 'drush' (the drush script in the current
 *            PATH) on remote servers.  You may also specify a different drush.php
 *            script explicitly.  You will need to set this when calling drush on
 *            a remote server if 'drush' is not in the PATH on that machine.
 *          'dispatch-using-alias'
 *            Optional. Defaults to FALSE.
 *            If specified as a non-empty value the drush command will be
 *            dispatched using the alias name on the command line, instead of
 *            the options from the alias being added to the command line
 *            automatically.
 * @param common_options
 *    Optional. Merged in with the options for each invocation.
 * @param backend_options
 *    Optional. Merged in with the backend options for each invocation.
 * @param default_command
 *    Optional. Used as the 'command' for any invocation that does not
 *    define a command explicitly.
 * @param default_site
 *    Optional. Used as the 'site' for any invocation that does not
 *    define a site explicitly.
 * @param context
 *    Optional. Passed in to proc_open if provided.
 *
 * @return
 *   If the command could not be completed successfully, FALSE.
 *   If the command was completed, this will return an associative array containing the data from drush_backend_output().
 */
function drush_backend_invoke_concurrent($invocations, $common_options = [], $common_backend_options = [], $default_command = NULL, $default_site = NULL, $context = NULL) {
  $index = 0;

  // Slice and dice our options in preparation to build a command string
  $invocation_options = [];
  foreach ($invocations as $invocation)  {
    $site_record = isset($invocation['site']) ? $invocation['site'] : $default_site;
    // NULL is a synonym to '@self', although the latter is preferred.
    if (!isset($site_record)) {
      $site_record = '@self';
    }
    // If the first parameter is not a site alias record,
    // then presume it is an alias name, and try to look up
    // the alias record.
    if (!is_array($site_record)) {
      $site_record = drush_sitealias_get_record($site_record);
    }
    $command = isset($invocation['command']) ? $invocation['command'] : $default_command;
    $args = isset($invocation['args']) ? $invocation['args'] : [];
    $command_options = isset($invocation['options']) ? $invocation['options'] : [];
    $backend_options = isset($invocation['backend-options']) ? $invocation['backend-options'] : [];
    // If $backend_options is passed in as a bool, interpret that as the value for 'integrate'
    if (!is_array($common_backend_options)) {
      $integrate = (bool)$common_backend_options;
      $common_backend_options = ['integrate' => $integrate];
    }

    $command_options += $common_options;
    $backend_options += $common_backend_options;

    $backend_options = _drush_backend_adjust_options($site_record, $command, $command_options, $backend_options);
    $backend_options += [
      'drush-script' => NULL,
    ];

    // Insure that contexts such as DRUSH_SIMULATE and NO_COLOR are included.
    $command_options += _drush_backend_get_global_contexts($site_record);

    // Add in command-specific options as well
    // $command_options += drush_command_get_command_specific_options($site_record, $command);

    $is_remote = array_key_exists('remote-host', $site_record);

    // Add in preflight option contexts (--include et. al)
    $preflightContextOptions = \Drush\Drush::config()->get(PreflightArgs::DRUSH_RUNTIME_CONTEXT_NAMESPACE, []);
    $preflightContextOptions['local'] = \Drush\Drush::config()->get('runtime.local', false);
    // If the command is local, also include the paths context.
    if (!$is_remote) {
      $preflightContextOptions += \Drush\Drush::config()->get(PreflightArgs::DRUSH_CONFIG_PATH_NAMESPACE, []);
    }
    foreach ($preflightContextOptions as $key => $value) {
      if ($value) {
        $command_options[$key] = $value;
      }
    }

    // If the caller has requested it, don't pull the options from the alias
    // into the command line, but use the alias name for dispatching.
    if (!empty($backend_options['dispatch-using-alias']) && isset($site_record['#name'])) {
      list($post_options, $commandline_options, $drush_global_options) = _drush_backend_classify_options([], $command_options, $backend_options);
      $site_record_to_dispatch = '@' . ltrim($site_record['#name'], '@');
    }
    else {
      list($post_options, $commandline_options, $drush_global_options) = _drush_backend_classify_options($site_record, $command_options, $backend_options);
      $site_record_to_dispatch = '';
    }
    if (array_key_exists('backend-simulate', $backend_options)) {
      $drush_global_options['simulate'] = TRUE;
    }
    $site_record += ['path-aliases' => [], '#env-vars' => []];
    $site_record['path-aliases'] += [
      '%drush-script' => $backend_options['drush-script'],
    ];

    $site = (array_key_exists('#name', $site_record) && !array_key_exists($site_record['#name'], $invocation_options)) ? $site_record['#name'] : $index++;
    $invocation_options[$site] = [
      'site-record' => $site_record,
      'site-record-to-dispatch' => $site_record_to_dispatch,
      'command' => $command,
      'args' => $args,
      'post-options' => $post_options,
      'drush-global-options' => $drush_global_options,
      'commandline-options' => $commandline_options,
      'command-options' => $command_options,
      'backend-options' => $backend_options,
    ];
  }

  // Calculate the length of the longest output label
  $max_name_length = 0;
  $label_separator = '';
  if (!array_key_exists('no-label', $common_options) && (count($invocation_options) > 1)) {
    $label_separator = array_key_exists('label-separator', $common_options) ? $common_options['label-separator'] : ' >> ';
    foreach ($invocation_options as $site => $item) {
      $backend_options = $item['backend-options'];
      if (!array_key_exists('#output-label', $backend_options)) {
        if (is_numeric($site)) {
          $backend_options['#output-label'] = ' * [@self.' . $site;
          $label_separator = '] ';
        }
        else {
          $backend_options['#output-label'] = $site;
        }
        $invocation_options[$site]['backend-options']['#output-label'] = $backend_options['#output-label'];
      }
      $name_len = strlen($backend_options['#output-label']);
      if ($name_len > $max_name_length) {
        $max_name_length = $name_len;
      }
      if (array_key_exists('#label-separator', $backend_options)) {
        $label_separator = $backend_options['#label-separator'];
      }
    }
  }
  // Now pad out the output labels and add the label separator.
  $reserve_margin = $max_name_length + strlen($label_separator);
  foreach ($invocation_options as $site => $item) {
    $backend_options = $item['backend-options'] + ['#output-label' => ''];
    $invocation_options[$site]['backend-options']['#output-label'] = str_pad($backend_options['#output-label'], $max_name_length, " ") . $label_separator;
    if ($reserve_margin) {
      $invocation_options[$site]['drush-global-options']['reserve-margin'] = $reserve_margin;
    }
  }

  // Now take our prepared options and generate the command strings
  $cmds = [];
  foreach ($invocation_options as $site => $item) {
    $site_record = $item['site-record'];
    $site_record_to_dispatch = $item['site-record-to-dispatch'];
    $command = $item['command'];
    $args = $item['args'];
    $post_options = $item['post-options'];
    $commandline_options = $item['commandline-options'];
    $command_options = $item['command-options'];
    $drush_global_options = $item['drush-global-options'];
    $backend_options = $item['backend-options'];
    $is_remote = array_key_exists('remote-host', $site_record);
    $is_different_site =
      $is_remote ||
      (isset($site_record['root']) && ($site_record['root'] != drush_get_context('DRUSH_DRUPAL_ROOT'))) ||
      (isset($site_record['uri']) && ($site_record['uri'] != drush_get_context('DRUSH_SELECTED_URI')));
    $os = drush_os($site_record);
    // If the caller did not pass in a specific path to drush, then we will
    // use a default value.  For commands that are being executed on the same
    // machine, we will use DRUSH_COMMAND, which is the path to the drush.php
    // that is running right now.
    $drush_path = $site_record['path-aliases']['%drush-script'];
    if (!$drush_path && !$is_remote && $is_different_site) {
      $drush_path = DRUSH_COMMAND;
    }
    $env_vars = $site_record['#env-vars'];
    $php = array_key_exists('php', $site_record) ? $site_record['php'] : (array_key_exists('php', $command_options) ? $command_options['php'] : NULL);
    $drush_command_path = drush_build_drush_command($drush_path, $php, $os, $is_remote, $env_vars);
    $cmd = _drush_backend_generate_command($site_record, $drush_command_path . " " . _drush_backend_argument_string($drush_global_options, $os) . " " . $site_record_to_dispatch . " " . $command, $args, $commandline_options, $backend_options) . ' 2>&1';
    $cmds[$site] = [
      'cmd' => $cmd,
      'post-options' => $post_options,
      'backend-options' => $backend_options,
    ];
  }

  return _drush_backend_invoke($cmds, $common_backend_options, $context);
}

/**
 * Find all of the drush contexts that are used to cache global values and
 * return them in an associative array.
 */
function _drush_backend_get_global_contexts($site_record) {
  $result = [];
  $global_option_list = drush_get_global_options(FALSE);
  foreach ($global_option_list as $global_key => $global_metadata) {
    if (is_array($global_metadata)) {
      $value = '';
      if (!array_key_exists('never-propagate', $global_metadata)) {
        if ((array_key_exists('propagate', $global_metadata))) {
          $value = drush_get_option($global_key);
        }
        elseif ((array_key_exists('propagate-cli-value', $global_metadata))) {
          $value = drush_get_option($global_key, '', 'cli');
        }
        elseif ((array_key_exists('context', $global_metadata))) {
          // If the context is declared to be a 'local-context-only',
          // then only put it in if this is a local dispatch.
          if (!array_key_exists('local-context-only', $global_metadata) || !array_key_exists('remote-host', $site_record)) {
            $value = drush_get_context($global_metadata['context'], []);
          }
        }
        if (!empty($value) || ($value === '0')) {
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
 *
 * Also, this function may modify $backend_options.
 */
function _drush_backend_classify_options($site_record, $command_options, &$backend_options) {
  // In 'POST' mode (the default, remove everything (except the items marked 'never-post'
  // in the global option list) from the commandline options and put them into the post options.
  // The post options will be json-encoded and sent to the command via stdin
  $global_option_list = drush_get_global_options(FALSE); // These should be in the command line.
  $additional_global_options = [];
  if (array_key_exists('additional-global-options', $backend_options)) {
    $additional_global_options = $backend_options['additional-global-options'];
    $command_options += $additional_global_options;
  }
  $method_post = ((!array_key_exists('method', $backend_options)) || ($backend_options['method'] == 'POST'));
  $post_options = [];
  $commandline_options = [];
  $drush_global_options = [];
  $drush_local_options = [];
  $additional_backend_options = [];
  foreach ($site_record as $key => $value) {
    if (!in_array($key, drush_sitealias_site_selection_keys())) {
      if ($key[0] == '#') {
        $backend_options[$key] = $value;
      }
      if (!isset($command_options[$key])) {
        if (array_key_exists($key, $global_option_list)) {
          $command_options[$key] = $value;
        }
      }
    }
  }
  if (array_key_exists('drush-local-options', $backend_options)) {
    $drush_local_options = $backend_options['drush-local-options'];
    $command_options += $drush_local_options;
  }
  if (!empty($backend_options['backend']) && empty($backend_options['interactive']) && empty($backend_options['fork'])) {
    $drush_global_options['backend'] = '2';
  }
  foreach ($command_options as $key => $value) {
    $global = array_key_exists($key, $global_option_list);
    $propagate = TRUE;
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
      // In METHOD GET, ignore options with array values
      elseif (!is_array($value)) {
        if ($global || array_key_exists($key, $additional_global_options)) {
          $drush_global_options[$key] = $value;
        }
        else {
          $commandline_options[$key] = $value;
        }
      }
    }
  }
  return [$post_options, $commandline_options, $drush_global_options, $additional_backend_options];
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
 *   If no commands were executed, FALSE.
 *
 *   If one command was executed, this will return an associative array containing
 *   the data from drush_backend_output().  The result code is stored
 *   in $result['error_status'] (0 == no error).
 *
 *   If multiple commands were executed, this will return an associative array
 *   containing one item, 'concurrent', which will contain a list of the different
 *   backend invoke results from each concurrent command.
 */
function _drush_backend_invoke($cmds, $common_backend_options = [], $context = NULL) {
  if (\Drush\Drush::simulate() && !array_key_exists('override-simulated', $common_backend_options) && !array_key_exists('backend-simulate', $common_backend_options)) {
    foreach ($cmds as $cmd) {
      drush_print(dt('Simulating backend invoke: !cmd', ['!cmd' => $cmd['cmd']]));
    }
    return FALSE;
  }
  foreach ($cmds as $cmd) {
    drush_log(dt('Backend invoke: !cmd', ['!cmd' => $cmd['cmd']]), 'command');
  }
  if (!empty($common_backend_options['interactive']) || !empty($common_backend_options['fork'])) {
    foreach ($cmds as $cmd) {
      $exec_cmd = $cmd['cmd'];
      if (array_key_exists('fork', $common_backend_options)) {
        $exec_cmd .= ' --quiet &';
      }

      $result_code = drush_shell_proc_open($exec_cmd);
      $ret = ['error_status' => $result_code];
    }
  }
  else {
    $process_limit = drush_get_option_override($common_backend_options, 'concurrency', 1);
    $procs = _drush_backend_proc_open($cmds, $process_limit, $context);
    $procs = is_array($procs) ? $procs : [$procs];

    $ret = [];
    foreach ($procs as $site => $proc) {
      if (($proc['code'] == DRUSH_APPLICATION_ERROR) && isset($common_backend_options['integrate'])) {
        drush_set_error('DRUSH_APPLICATION_ERROR', dt("The external command could not be executed due to an application error."));
      }

      if ($proc['output']) {
        $values = drush_backend_parse_output($proc['output'], $proc['backend-options'], $proc['outputted']);
        if (is_array($values)) {
          $values['site'] = $site;
          if (empty($ret)) {
            $ret = $values;
          }
          elseif (!array_key_exists('concurrent', $ret)) {
            $ret = ['concurrent' => [$ret, $values]];
          }
          else {
            $ret['concurrent'][] = $values;
          }
        }
        else {
          $ret = drush_set_error('DRUSH_FRAMEWORK_ERROR', dt("The command could not be executed successfully (returned: !return, code: !code)", ["!return" => $proc['output'], "!code" =>  $proc['code']]));
        }
      }
    }
  }
  return empty($ret) ? FALSE : $ret;
}

/**
 * Helper function that generates an anonymous site alias specification for
 * the given parameters.
 */
function drush_backend_generate_sitealias($backend_options) {
  // Ensure default values.
  $backend_options += [
    'remote-host' => NULL,
    'remote-user' => NULL,
    'ssh-options' => NULL,
    'drush-script' => NULL,
    'env-vars' => NULL
  ];
  return [
    'remote-host' => $backend_options['remote-host'],
    'remote-user' => $backend_options['remote-user'],
    'ssh-options' => $backend_options['ssh-options'],
    '#env-vars' => $backend_options['env-vars'],
    'path-aliases' => [
      '%drush-script' => $backend_options['drush-script'],
    ],
  ];
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
 *   '#env-vars'
 *      Optional. An associative array of environmental variables to prefix the Drush command with.
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
function _drush_backend_generate_command($site_record, $command, $args = [], $command_options = [], $backend_options = []) {
  $site_record += [
    'remote-host' => NULL,
    'remote-user' => NULL,
    'ssh-options' => NULL,
    'path-aliases' => [],
  ];
  $backend_options += [
    '#tty' => FALSE,
  ];

  $hostname = $site_record['remote-host'];
  $username = $site_record['remote-user'];
  $ssh_options = $site_record['ssh-options']; // TODO: update this (maybe make $site_record an AliasRecord)
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
  $command = implode(' ', array_filter($cmd, 'strlen'));
  if (isset($hostname)) {
    $username = (isset($username)) ? drush_escapeshellarg($username) . "@" : '';
    $ssh_options = $site_record['ssh-options']; // TODO: update
    $ssh_options = (isset($ssh_options)) ? $ssh_options : \Drush\Drush::config()->get('ssh.options', "-o PasswordAuthentication=no");

    $ssh_cmd[] = "ssh";
    $ssh_cmd[] = $ssh_options;
    if ($backend_options['#tty']) {
      $ssh_cmd[] = '-t';
    }
    $ssh_cmd[] = $username . drush_escapeshellarg($hostname);
    $ssh_cmd[] = drush_escapeshellarg($command . ' 2>&1');

    // Remove NULLs and separate with spaces
    $command = implode(' ', array_filter($ssh_cmd, 'strlen'));
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
  $options = [];

  foreach ($data as $key => $value) {
    if (!is_array($value) && !is_object($value) && isset($value)) {
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
    $read = [$fp];
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
