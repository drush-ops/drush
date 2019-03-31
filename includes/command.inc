<?php

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Drush\Drush;
use Drush\Log\LogLevel;
use Webmozart\PathUtil\Path;
use Consolidation\AnnotatedCommand\AnnotationData;
use Drush\Command\DrushInputAdapter;
use Consolidation\SiteAlias\SiteAlias;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @defgroup dispatching Command dispatching functions.
 * @{
 *
 * These functions handle command dispatching, and can
 * be used to programmatically invoke Drush commands in
 * different ways.
 */

/**
 * Invoke a command in a new process, targeting the site specified by
 * the provided site alias record.
 *
 * @param array|string $site_alias_record
 *  The site record to execute the command on.  Use '@self' to run on the current site.
 * @param string $command_name
 *  The command to invoke.
 * @param array $commandline_args
 *  The arguments to pass to the command.
 * @param array $commandline_options
 *  The options (e.g. --select) to provide to the command.
 * @param mixed $backend_options
 *   TRUE - integrate errors
 *   FALSE - do not integrate errors
 *   array - @see drush_backend_invoke_concurrent
 *     There are also several options that _only_ work when set in
 *     this parameter.  They include:
 *      'invoke-multiple'
 *        If $site_alias_record represents a single site, then 'invoke-multiple'
 *        will cause the _same_ command with the _same_ arguments and options
 *        to be invoked concurrently (e.g. for running concurrent batch processes).
 *      'concurrency'
 *        Limits the number of concurrent processes that will run at the same time.
 *        Defaults to '4'.
 *      'override-simulated'
 *        Forces the command to run, even in 'simulated' mode. Useful for
 *        commands that do not change any state on the machine, e.g. to fetch
 *        database information for sql-sync via sql-conf.
 *      'interactive'
 *        Overrides the backend invoke process to run commands interactively.
 *      'fork'
 *        Overrides the backend invoke process to run non blocking commands in
 *        the background. Forks a new process by adding a '&' at the end of the
 *        command. The calling process does not receive any output from the child
 *        process. The fork option is used to spawn a process that outlives its
 *        parent.
 *
 * @return
 *   If the command could not be completed successfully, FALSE.
 *   If the command was completed, this will return an associative
 *   array containing the results of the API call.
 *   @see drush_backend_get_result()
 *
 * Do not change the signature of this function!  drush_invoke_process
 * was one of the key Drush APIs.  See http://drupal.org/node/1152908
 *
 * @deprecated See Drush::drush().
 */
function drush_invoke_process($site_alias_record, $command_name, $commandline_args = [], $commandline_options = [], $backend_options = TRUE) {
  if ($site_alias_record instanceof SiteAlias) {
    $site_alias_record = $site_alias_record->legacyRecord();
  }

  $invocations[] = [
    'site' => $site_alias_record,
    'command' => $command_name,
    'args' => $commandline_args
  ];
  $invoke_multiple = drush_get_option_override($backend_options, 'invoke-multiple', 0);
  if ($invoke_multiple) {
    $invocations = array_fill(0, $invoke_multiple, $invocations[0]);
  }

  return drush_backend_invoke_concurrent($invocations, $commandline_options, $backend_options);
}

/**
 * Substrings to ignore during commandfile and site alias searching.
 * TODO: Do we do any searching in the new code that should be filtered like this?
 */
function drush_filename_blacklist() {
  $blacklist = ['.', '..', 'drush_make', 'examples', 'tests', 'disabled', 'gitcache', 'cache'];
  for ($v=4; $v<=(DRUSH_MAJOR_VERSION)+3; ++$v) {
    if ($v != DRUSH_MAJOR_VERSION) {
      $blacklist[] = 'drush' . $v;
    }
  }
  $blacklist = array_merge($blacklist, drush_get_option_list('exclude'));
  return $blacklist;
}
