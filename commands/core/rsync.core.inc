<?php

use Drush\Log\LogLevel;

/*
 * Implements COMMAND hook init.
 */
function drush_core_rsync_init() {
  // Try to get @self defined when --uri was not provided.
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
}

/**
 * A command callback.
 *
 * @param source
 *   A site alias ("@dev") or site specification ("/path/to/drupal#mysite.com")
 *   followed by an optional path (":path/to/sync"), or any path
 *   that could be passed to rsync ("user@server.com:/path/to/dir/").
 * @param destination
 *   Same format as source.
 * @param additional_options
 *   An array of options that overrides whatever was passed in on
 *   the command line (like the 'process' context, but only for
 *   the scope of this one call).
 */
function drush_core_rsync($source, $destination, $additional_options = array()) {
  // Preflight source in case it defines aliases used by the destination
  _drush_sitealias_preflight_path($source);
  // After preflight, evaluate file paths.  We evaluate destination paths first, because
  // there is a first-one-wins policy with --exclude-paths, and we want --target-command-specific
  // to take precedence over --source-command-specific.
  $destination_settings = drush_sitealias_evaluate_path($destination, $additional_options, FALSE, "rsync", 'target-');
  $source_settings = drush_sitealias_evaluate_path($source, $additional_options, FALSE, "rsync", 'source-');
  $source_path = $source_settings['evaluated-path'];
  $destination_path = $destination_settings['evaluated-path'];

  if (!isset($source_settings)) {
    return drush_set_error('DRUSH_BAD_PATH', dt('Could not evaluate source path !path.', array('!path' => $source)));
  }
  if (!isset($destination_settings)) {
    return drush_set_error('DRUSH_BAD_PATH', dt('Could not evaluate destination path !path.', array('!path' => $destination)));
  }

  // If the user path is the same for the source and the destination, then
  // always add a slash to the end of the source.  If the user path is not
  // the same in the source and the destination, then you need to know how
  // rsync paths work, and put on the trailing '/' if you want it.
  if ($source_settings['user-path'] == $destination_settings['user-path']) {
    $source_path .= '/';
  }
  // Prompt for confirmation. This is destructive.
  if (!drush_get_context('DRUSH_SIMULATE')) {
    drush_print(dt("You will delete files in !target and replace with data from !source", array('!source' => $source_path, '!target' => $destination_path)));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  // Next, check to see if both the source and the destination are remote.
  // If so, then we'll process this as an rsync from source to local,
  // followed by an rsync from local to the destination.
  if (drush_sitealias_is_remote_site($source_settings) && drush_sitealias_is_remote_site($destination_settings)) {
    return _drush_core_rsync_both_remote($source, $destination, $additional_options, $source_path);
  }

  // Exclude settings is the default only when both the source and
  // the destination are aliases or site names.  Therefore, include
  // settings will be the default whenever either the source or the
  // destination contains a : or a /.
  $include_settings_is_default = (strpos($source . $destination, ':') !== FALSE) || (strpos($source . $destination, '/') !== FALSE);

  $options = _drush_build_rsync_options($additional_options, $include_settings_is_default);

  // Get all of the args and options that appear after the command name.
  $original_args = drush_get_original_cli_args_and_options();
  foreach ($original_args as $original_option) {
    if ($original_option{0} == '-') {
      $options .= ' ' . $original_option;
    }
  }

  drush_backend_set_result($destination_path);
  // Go ahead and call rsync with the paths we determined
  return drush_core_exec_rsync($source_path, $destination_path, $options);
}

/**
 * Make a direct call to rsync after the source and destination paths
 * have been evaluated.
 *
 * @param $source
 *   Any path that can be passed to rsync.
 * @param $destination
 *   Any path that can be passed to rsync.
 * @param $additional_options
 *   An array of options that overrides whatever was passed in on the command
 *   line (like the 'process' context, but only for the scope of this one
 *   call).
 * @param $include_settings_is_default
 *   If TRUE, then settings.php will be transferred as part of the rsync unless
 *   --exclude-conf is specified.  If FALSE, then settings.php will be excluded
 *   from the transfer unless --include-conf is specified.
 * @param $live_output
 *   If TRUE, output goes directly to the terminal using system(). If FALSE,
 *   rsync is executed with drush_shell_exec() with output in
 *   drush_shell_exec_output().
 *
 * @return
 *   TRUE on success, FALSE on failure.
 */
function drush_core_call_rsync($source, $destination, $additional_options = array(), $include_settings_is_default = TRUE, $live_output = TRUE) {
  $options = _drush_build_rsync_options($additional_options, $include_settings_is_default);
  return drush_core_exec_rsync($source, $destination, $options, $additional_options, $live_output);
}

function drush_core_exec_rsync($source, $destination, $options, $additional_options = array(), $live_output = TRUE) {
  $ssh_options = drush_get_option_override($additional_options, 'ssh-options', '');
  $exec = "rsync -e 'ssh $ssh_options' $options $source $destination";

  if ($live_output) {
    $exec_result = drush_op_system($exec);
    $result = ($exec_result == 0);
  }
  else {
    $result = drush_shell_exec($exec);
  }

  if (!$result) {
    drush_set_error('DRUSH_RSYNC_FAILED', dt("Could not rsync from !source to !dest", array('!source' => $source, '!dest' => $destination)));
  }

  return $result;
}

function _drush_build_rsync_options($additional_options, $include_settings_is_default = TRUE) {
  $options = '';
  // Exclude vcs reserved files.
  if (!_drush_rsync_option_exists('include-vcs', $additional_options)) {
    $vcs_files = drush_version_control_reserved_files();
    foreach ($vcs_files as $file) {
      $options .= ' --exclude="'.$file.'"';
    }
  }
  else {
    unset($additional_options['include-vcs']);
  }

  $mode = '-akz';
  // Process --include-paths and --exclude-paths options the same way
  foreach (array('include', 'exclude') as $include_exclude) {
    // Get the option --include-paths or --exclude-paths and explode to an array of paths
    // that we will translate into an --include or --exclude option to pass to rsync
    $inc_ex_path = explode(PATH_SEPARATOR, drush_get_option($include_exclude . '-paths', ''));
    foreach ($inc_ex_path as $one_path_to_inc_ex) {
      if (!empty($one_path_to_inc_ex)) {
        $options .= ' --' . $include_exclude . '="' . $one_path_to_inc_ex . '"';
      }
    }
    // Remove stuff inserted by evaluate path
    unset($additional_options[$include_exclude . '-paths']);
    unset($additional_options[$include_exclude . '-files-processed']);
  }
  // drush_core_rsync passes in $include_settings_is_default such that
  // 'exclude-conf' is the default when syncing from one alias to
  // another, and 'include-conf' is the default when a path component
  // is included.
  if ($include_settings_is_default ? _drush_rsync_option_exists('exclude-conf', $additional_options) : !_drush_rsync_option_exists('include-conf', $additional_options)) {
    $options .= ' --exclude="settings.php"';
    unset($additional_options['exclude-conf']);
  }
  if (_drush_rsync_option_exists('exclude-sites', $additional_options)) {
    $options .= ' --include="sites/all" --exclude="sites/*"';
    unset($additional_options['exclude-sites']);
  }
  if (_drush_rsync_option_exists('mode', $additional_options)) {
    $mode = "-" . drush_get_option_override($additional_options, 'mode');
    unset($additional_options['mode']);
  }
  if (drush_get_context('DRUSH_VERBOSE')) {
    // the drush_op() will be verbose about the command that gets executed.
    $mode .= 'v';
    $options .= ' --stats --progress';
  }

  // Check if the user has set $options['rsync-version'] to enable rsync legacy version support.
  // Drush was written for rsync 2.6.9 or later, so assume that version if nothing was explicitly set.
  $rsync_version = drush_get_option(array('rsync-version','source-rsync-version','target-rsync-version'), '2.6.9');
  $options_to_exclude = array('ssh-options');
  foreach ($additional_options as $test_option => $value) {
    // Downgrade some options for older versions of rsync
    if ($test_option == 'remove-source-files') {
      if (version_compare($rsync_version, '2.6.4', '<')) {
        $test_option = NULL;
        drush_log('Rsync does not support --remove-sent-files prior to version 2.6.4; some temporary files may remain undeleted.', LogLevel::WARNING);
      }
      elseif (version_compare($rsync_version, '2.6.9', '<')) {
        $test_option = 'remove-sent-files';
      }
    }
    if ((isset($test_option)) && !in_array($test_option, $options_to_exclude) && (isset($value)  && !is_array($value))) {
      if (($value === TRUE) || (!isset($value))) {
        $options .= " --$test_option";
      }
      else {
        $options .= " --$test_option=" . escapeshellarg($value);
      }
    }
  }

  return $mode . $options;
}

function _drush_rsync_option_exists($option, $additional_options) {
  if (array_key_exists($option, $additional_options)) {
    return TRUE;
  }
  else {
    return drush_get_option($option, FALSE);
  }
}

/**
 * Handle an rsync operation from a remote site to a remote
 * site by first rsync'ing to a local location, and then
 * copying that location to its final destination.
 */
function _drush_core_rsync_both_remote($source, $destination, $additional_options, $source_path) {
  $options = $additional_options + drush_redispatch_get_options();

  // Make a temporary directory to copy to.  There are three
  // cases to consider:
  //
  // 1. rsync @src:file.txt @dest:location
  // 2. rsync @src:dir @dest:location
  // 3. rsync @src:dir/ @dest:location
  //
  // We will explain each of these in turn.
  //
  // 1. Copy a single file.  We'll split this up like so:
  //
  //    rsync @src:file.txt /tmp/tmpdir
  //    rsync /tmp/tmpdir/file.txt @dest:location
  //
  // Since /tmp/tmpdir is empty, we could also rsync from
  // '/tmp/tmpdir/' if we wished.
  //
  // 2. Copy a directory. A directory with the same name
  // is copied to the destination.  We'll split this up like so:
  //
  //    rsync @src:dir /tmp/tmpdir
  //    rsync /tmp/tmpdir/dir @dest:location
  //
  // The result is that everything in 'dir' is copied to @dest,
  // and ends up in 'location/dir'.
  //
  // 3. Copy the contents of a directory.  We will split this
  // up as follows:
  //
  //    rsync @src:dir/ /tmp/tmpdir
  //    rsync /tmp/tmpdir/ @dest:location
  //
  // After the first rsync, everything in 'dir' will end up in
  // tmpdir.  The second rsync copies everything in tmpdir to
  // @dest:location without creating an encapsulating folder
  // in the destination (i.e. there is no 'tmpdir' in the destination).
  //
  // All three of these cases need to be handled correctly in order
  // to ensure the correct results.  In all cases the first
  // rsync always copies to $tmpDir, however the second rsync has
  // two cases that depend on the source path.  If the source path ends
  // in /, the contents of a directory have been copied to $tmpDir, and
  // the contents of $tmpDir must be copied to the destination.  Otherwise,
  // a specific file or directory has been copied to $tmpDir and that
  // specific item, identified by basename($source_path) must be copied to
  // the destination.

  $putInTmpPath = drush_tempdir();
  $getFromTmpPath = "$putInTmpPath/";
  if (substr($source_path, -1) !== '/') {
    $getFromTmpPath .= basename($source_path);
  }

  // Copy from the source to the temporary location. Exit on failure.
  $values = drush_invoke_process('@self', 'core-rsync', array($source, $putInTmpPath), $options);
  if ($values['error'] != 0) {
    return FALSE;
  }

  // Copy from the temporary location to the final destination.
  $values = drush_invoke_process('@self', 'core-rsync', array($getFromTmpPath, $destination), $options);

  return $values['error'] == 0;
}
