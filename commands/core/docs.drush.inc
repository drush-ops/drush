<?php

/**
 * @file
 *   Documentation commands providing various topics.
 */

/**
 * Implementation of hook_drush_help().
 */
function docs_drush_help($section) {
  switch ($section) {
    case 'meta:docs:title':
      return dt('Documentation commands');
    case 'meta:docs:summary':
      return dt('Show information on various drush topics.');
  }
}

/**
 * Implementation of hook_drush_command().
 *
 * @return
 *   An associative array describing your command(s).
 */
function docs_drush_command() {
  $docs_dir = drush_get_context('DOC_PREFIX', DRUSH_BASE_PATH);

  //
  // Topic commands.
  // Any commandfile may add topics.
  // Set 'topic' => TRUE to indicate the command is a topic (REQUIRED)
  // Begin the topic name with the name of the commandfile (just like
  // any other command).
  //
  $items['docs-readme'] = array(
    'description' => 'README.md',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/README.md'),
    'aliases' => array('docs:readme'),
  );
  $items['docs-bisect'] = array(
    'description' => 'git bisect and Drush may be used together to find the commit an error was introduced in.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/git-bisect.example.sh'),
    'aliases' => array('docs:bisect'),
  );
  $items['docs-bashrc'] = array(
    'description' => 'Bashrc customization examples for Drush.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/example.bashrc'),
    'aliases' => array('docs:bashrc'),
  );
  $items['docs-configuration'] = array(
    'description' => 'Configuration overview with examples from example.drushrc.php.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/example.drushrc.php'),
    'aliases' => array('docs:configuration'),
  );
  $items['docs-config-exporting'] = array(
    'description' => 'Drupal configuration export instructions, including customizing configuration by environment.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/config-exporting.md'),
    'aliases' => array('docs:config:exporting'),
  );
  $items['docs-aliases'] = array(
    'description' => 'Site aliases overview on creating your own aliases for commonly used Drupal sites with examples from example.aliases.drushrc.php.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/example.aliases.drushrc.php'),
    'aliases' => array('docs:aliases'),
  );
  $items['docs-ini-files'] = array(
    'description' => 'php.ini or drush.ini configuration to set PHP values for use with Drush.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/example.drush.ini'),
    'aliases' => array('docs:ini-files'),
  );
  $items['docs-bastion'] = array(
    'description' => 'Bastion server configuration: remotely operate on a Drupal sites behind a firewall.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/bastion.md'),
    'aliases' => array('docs:bastion'),
  );
  $items['docs-bootstrap'] = array(
    'description' => 'Bootstrap explanation: how Drush starts up and prepares the Drupal environment for use with the command.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/bootstrap.md'),
    'aliases' => array('docs:bootstrap'),
  );
  $items['docs-cron'] = array(
    'description' => 'Crontab instructions for running your Drupal cron tasks via `drush cron`.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/cron.md'),
    'aliases' => array('docs:cron'),
  );
  $items['docs-scripts'] = array(
    'description' => 'Shell script overview on writing simple sequences of Drush statements.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/shellscripts.md'),
    'aliases' => array('docs:scripts'),
  );
  $items['docs-shell-aliases'] = array(
    'description' => 'Shell alias overview on creating your own aliases for commonly used Drush commands.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/shellaliases.md'),
    'aliases' => array('docs:shell-aliases'),
  );
  $items['docs-commands'] = array(
    'description' => 'Drush command instructions on creating your own Drush commands.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/commands.md'),
    'aliases' => array('docs:commands'),
  );
  $items['docs-errorcodes'] = array(
    'description' => 'Error code list containing all identifiers used with drush_set_error.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'aliases' => array('docs:errorcodes'),
  );
  $items['docs-api'] = array(
    'description' => 'Drush API',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/drush.api.php'),
    'aliases' => array('docs:api'),
  );
  $items['docs-context'] = array(
    'description' => 'Contexts overview explaining how Drush manages command line options and configuration file settings.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/context.md'),
    'aliases' => array('docs:context'),
  );
  $items['docs-examplescript'] = array(
    'description' => 'Example Drush script.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/helloworld.script'),
    'aliases' => array('docs:examplescript'),
  );
  $items['docs-examplecommand'] = array(
    'description' => 'Example Drush command file.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/sandwich.drush.inc'),
    'aliases' => array('docs:examplecommand'),
  );
  $items['docs-example-sync-extension'] = array(
    'description' => 'Example Drush commandfile that extends sql-sync to enable development modules in the post-sync hook.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/sync_enable.drush.inc'),
    'aliases' => array('docs:example-sync-extension'),
  );
  $items['docs-example-sync-via-http'] = array(
    'description' => 'Example Drush commandfile that extends sql-sync to allow transfer of the sql dump file via http rather than ssh and rsync.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/sync_via_http.drush.inc'),
    'aliases' => array('docs:example-sync-via-http'),
  );
  $items['docs-policy'] = array(
    'description' => 'Example policy file.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/examples/policy.drush.inc'),
    'aliases' => array('docs:policy'),
  );
  $items['docs-strict-options'] = array(
    'description' => 'Strict option handling, and how commands that use it differ from regular Drush commands.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_print_file',
    'callback arguments' => array($docs_dir . '/docs/strict-options.md'),
    'aliases' => array('docs:strict-options'),
  );
  return $items;
}

/**
 * docs-errorcodes command.  Print a list of all error codes
 * that can be found.
 */
function drush_docs_errorcodes() {
  $header = <<<EOD
==== Drush Error Codes ====

Drush error codes are alphanumeric constants that represent an unrecoverable error condition that may arise during the execution of some command.  They are set by the following function:

  return drush_set_error('DRUSH_ERROR_CODE', dt('Error message.'));

In general, any drush command that calls drush_set_error is expected to also return FALSE as its function result.  The drush_set_error function returns FALSE to make it easy to exit with an error code.  Error codes are returned as part of the drush backend invoke process, which is used by drush API functions such as drush_invoke_process.  An example of how to test for a specific error code is shown below:

  \$result = drush_invoke_process('@self', 'some-command');
  if (array_key_exists('DRUSH_ERROR_CODE', \$result['error_log'])) {
    // handle ocurrances of DRUSH_ERROR_CODE here
  }

Some of the available drush error codes are listed in the table below.


EOD;

  // Find all of the files that we will search for error messages.
  // Start with all of the commandfiles.
  $commandfiles = drush_commandfile_list();
  $files = array_flip($commandfiles);
  // In addition to the commandfiles, we will also look for files
  // that drush will load when executing a command (e.g
  // updatecode.pm.inc)
  $commands = drush_get_commands();
  foreach ($commands as $command_name => $command) {
    $files = array_merge($files, drush_command_get_includes($command_name));
  }
  // We will also search through all of the .inc files in the
  // drush includes directory
  $drush_include_files = drush_scan_directory(DRUSH_BASE_PATH . '/includes', '/.*\.inc$/', array('.', '..', 'CVS'), 0, FALSE);
  foreach ($drush_include_files as $filename => $info) {
    $files[$filename] = 'include';
  }

  // Extract error messages from all command files
  $error_list = array();
  foreach ($files as $file => $commandfile) {
    _drush_docs_find_set_error_calls($error_list, $file, $commandfile);
  }
  // Order error messages alphabetically by key
  ksort($error_list);
  // Convert to a table
  $data = array();
  foreach ($error_list as $error_code => $error_messages) {
    $data[] = array($error_code, '-', implode("\n", $error_messages));
  }

  $tmpfile = drush_tempnam('drush-errorcodes.');
  file_put_contents($tmpfile, $header);
  $handle = fopen($tmpfile, 'a');
  drush_print_table($data, FALSE, array(0 => 35), $handle);
  fclose($handle);
  drush_print_file($tmpfile);
}

/**
 * Search through a php source file looking for calls to
 * the function drush_set_error.  If found, and if the
 * first parameter is an uppercase alphanumeric identifier,
 * then record the error code and the error message in our table.
 */
function _drush_docs_find_set_error_calls(&$error_list, $filename, $shortname) {
  $lines = file($filename);
  foreach ($lines as $line) {
    $matches = array();
    // Find the error code after the drush_set_error call.  The error code
    // should consist of uppercase letters and underscores only (numbers thrown in just in case)
    $match_result = preg_match("/.*drush_set_error[^'\"]['\"]([A-Z0-9_]*)['\"][^,]*,[^'\"]*(['\"])/", $line, $matches);
    if ($match_result) {
      $error_code = $matches[1];
      $quote_char = $matches[2];
      $error_message = "";
      $message_start = strlen($matches[0]) - 1;

      // Regex adapted from http://stackoverflow.com/questions/1824325/regex-expression-for-escaped-quoted-string-wont-work-in-phps-preg-match-allif ($quote_char == '"') {
      if ($quote_char == '"') {
        $regex = '/"((?:[^\\\]*?(?:\\\")?)*?)"/';
      }
      else {
        $regex = "/'((?:[^\\\]*?(?:\\\')?)*?)'/";
      }
      $match_result = preg_match($regex, $line, $matches, 0, $message_start);

      if ($match_result) {
        $error_message = $matches[1];
      }
      $error_list[$error_code] = array_key_exists($error_code, $error_list) ? array_merge($error_list[$error_code], array($error_message)) : array($error_message);
    }
  }
}
