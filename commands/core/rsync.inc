<?php
// $Id$

/**
 * Entrypoint for drush rsync.
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
  // Preflight destination in case it defines aliases used by the source
  _drush_core_evaluate_path($destination, $additional_options);
  // After preflight, evaluate file paths
  $source_settings = _drush_core_evaluate_path($source, $additional_options);
  $destination_settings = _drush_core_evaluate_path($destination, $additional_options);
  $source_path = $source_settings['rsync-path'];
  $destination_path = $destination_settings['rsync-path'];

  // Check to see if this is an rsync multiple command (multiple sources and multiple destinations)
  $is_multiple = drush_do_multiple_command('rsync', $source_settings, $destination_settings, TRUE);

  if ($is_multiple === FALSE) {
    // Prompt for confirmation. This is destructive.
    if (!drush_get_context('DRUSH_SIMULATE')) {
      drush_print(dt("You will destroy data from !target and replace with data from !source", array('!source' => $source_path, '!target' => $destination_path)));
      if (!drush_confirm(dt('Do you really want to continue?'))) {
        // was: return drush_set_error('CORE_SYNC_ABORT', 'Aborting.');
        drush_die('Aborting.');
      }
    }
    
    // Exclude settings is the default only when both the source and
    // the destination are aliases or site names.  Therefore, include
    // settings will be the default whenever either the source or the
    // destination contains a : or a /.
    $include_settings_is_default = (strpos($source . $destination, ':') !== FALSE) || (strpos($source . $destination, '/') !== FALSE);
    
    // Go ahead and call rsync with the paths we determined
    drush_core_call_rsync($source_path, $destination_path, $additional_options, $include_settings_is_default);
  }
}

/**
 * Make a direct call to rsync after the source and destination paths
 * have been evaluated.
 *
 * @param source
 *   Any path that can be passed to rsync.
 * @param destination
 *   Any path that can be passed to rsync.
 * @param additional_options
 *   An array of options that overrides whatever was passed in on
 *   the command line (like the 'process' context, but only for
 *   the scope of this one call).
 * @param include_settings_is_default
 *   If TRUE, then settings.php will be transferred as part of the
 *   rsync unless --exclude-conf is specified.  If FALSE, then
 *   settings.php will be excluded from the transfer unless
 *   --include-conf is specified.
 */
function drush_core_call_rsync($source, $destination, $additional_options = array(), $include_settings_is_default = TRUE) {
  $options = ' --exclude="*.svn*"';
  $mode = '-az';
  // Process --include-path and --exclude-path options the same way 
  foreach (array('include', 'exclude') as $include_exclude) {
    // Get the option --include-path or --exclude path and explode to an array of paths
    // that we will translate into an --include or --exclude option to pass to rsync
    $inc_ex_path = explode(',', drush_get_option($include_exclude . '-path', ''));
    foreach ($inc_ex_path as $one_path_to_inc_ex) {
      if (!empty($one_path_to_inc_ex)) {
        $options .= ' --' . $include_exclude . '="' . $one_path_to_inc_ex . '"';
      }
    }
  }
  // drush_core_rsync passes in $include_settings_is_default such that
  // 'exclude-conf' is the default when syncing from one alias to
  // another, and 'include-conf' is the default when a path component
  // is included.
  if ($include_settings_is_default ? _drush_rsync_option_exists('exclude-conf', $additional_options) : !_drush_rsync_option_exists('include-conf', $additional_options)) {
    $options .= ' --exclude="settings.php"';
  }
  if (_drush_rsync_option_exists('exclude-sites', $additional_options)) {
    $options .= ' --include="sites/all" --exclude="sites/*"';
  }
  if (_drush_rsync_option_exists('mode', $additional_options)) {
    $mode = "-" . _drush_rsync_get_option($additional_options, 'mode');
  }
  if (drush_get_context('DRUSH_VERBOSE')) {
    // the drush_op() will be verbose about the command that gets executed.
    $mode .= 'v';
    $options .= ' --stats --progress';
  }
  $rsync_available_options = array(
    // unary options
    'archive', // -a
    'recursive', // -r
    'relative', // -R
    'backup', // -b
    'update', // -u
    'checksum', // -c
    'dirs', // -d
    'links', // -l
    'copy-links', // -L
    'copy-dirlinks', // -k
    'keep-dirlinks', // -K
    'hard-links', // -H
    'perms', // -p
    'executability', // -E
    'acls', // -A
    'xattrs', // -X
    'owner', // -o
    'group', // -g
    'times', // -t
    'omit-dir-times', // -O
    'sparse', // -S
    'dry-run', // -n
    'whole-file', // -W
    'one-file-system', // -x
    'prune-empty-dirs', // -m
    'ignore-times', // -I
    'fuzzy', // -y
    'cvs-exclude', // -C
    'compress', // -Z
    'protect-args', // -s
    '8-bit-output', // -8
    'human-readable', // -h
    'itemize-changes', // -i
    'copy-unsafe-links',
    'safe-links',
    'no-implied-dirs',
    'inplace',
    'append',
    'append-verify',
    'existing',
    'remove-source-files',
    'delete',
    'delete-before',
    'delete-during',
    'delete-delay',
    'delete-after',
    'delete-excluded',
    'ignore-errors',
    'force',
    'ignore-existing',
    'partial',
    'delay-updates',
    'numeric-ids',
    'size-only',
    'blocking-io',
    'stats',
    'progress',
    'list-only',
    // options with values
    'block-size',
    'backup-dir',
    'suffix',
    'chmod',
    'rsync-path',
    'modify-window',
    'compare-dest',
    'copy-dest',
    'link-dest',
    'skip-compress',
    'filter',
    'exclude',
    'include',
    'address',
    'port',
    'sockopts',
    'out-format',
    'bwlimit',
    'iconv',
    'checksum-seed',
    'max-delete',
    'max-size',
    'min-size',
    'partial-dir',
    'timeout',
    'temp-dir',
    'compress-level',
    'out-format',
    'protocol',
    );
  foreach ($rsync_available_options as $test_option) {
    $value = _drush_rsync_get_option($additional_options, $test_option);
    if (isset($value)) {
      if ($value === TRUE) {
        $options .= " --$test_option";
      }
      else {
        $options .= " --$test_option=" . escapeshellarg($value);
      }
    }
  }

  $ssh_options = _drush_rsync_get_option($additional_options, 'ssh-options', '');
  $exec = "rsync -e 'ssh $ssh_options' $mode$options $source $destination";

  $exec_result = drush_op('system', $exec) !== FALSE;

  return $exec_result;
}

/**
 * Evaluate a path from its shorthand form to a literal path
 * usable by rsync.
 *
 * A path is "machine:/path" or "machine:path" or "/path" or "path".
 * 'machine' might instead be an alias record, or the name
 * of a site in the 'sites' folder.  'path' might be (or contain)
 * '%root' or some other path alias.  This function will examine
 * all components of the path and evaluate them as necessary to
 * come to the final path.
 *
 * @param path
 *   The path to evaluate
 * @param additional_options
 *   An array of options that overrides whatever was passed in on
 *   the command line (like the 'process' context, but only for
 *   the scope of this one call).
 * @return
 *   The site record for the machine specified in the path, if any,
 *   with the path to pass to rsync (including the machine specifier)
 *   in the 'rsync-path' item.
 */
function _drush_core_evaluate_path($path, &$additional_options) {
  $site_alias_settings = array();
  $path_aliases = array();
  $remote_user = '';
  // Parse site aliases if there is a colon in the path
  $colon_pos = strpos($path, ':');
  if ($colon_pos !== FALSE) {
    $alias = substr($path, 0, $colon_pos);
    $path = substr($path, $colon_pos + 1);
    $site_alias_settings = drush_sitealias_get_record($alias);
    if (empty($site_alias_settings)) {
      return NULL;
    }
    $machine = $alias;
  }
  else {
    $machine = '';
    // if the path is a site alias or a local site...
    $site_alias_settings = drush_sitealias_get_record($path);
    if (!empty($site_alias_settings) || drush_is_local_host($path)) {
      $alias = $path;
      $path = '';
    }
  }
  
  if (!empty($site_alias_settings)) {
    // Apply any options from this alias that might affect our rsync
    drush_sitealias_set_alias_context($site_alias_settings);
    
    // Use 'remote-host' from settings if available; otherwise site is local
    if (array_key_exists('remote-host', $site_alias_settings)) {
      if (array_key_exists('remote-user', $site_alias_settings)) {
        $remote_user = $site_alias_settings['remote-user'] . '@';
      }
      $machine = $remote_user . $site_alias_settings['remote-host'];
    }
    else {
      $machine = '';
    }
  }
  else {
    // Strip the machine portion of the path if the
    // alias points to the local machine.
    if (drush_is_local_host($machine)) {
      $machine = '';
    }
    else {
      $machine = "$remote_user$machine";
    }
  }

  // If the --exclude-other-sites option is specified, then
  // convert that into --include-path='%site' and --exclude-sites.
  if (_drush_rsync_get_option($additional_options, 'exclude-other-sites', FALSE) && !_drush_rsync_get_option($additional_options, 'exclude-other-sites-processed', FALSE, 'process')) {
    $additional_options['include-path'] = '%site,' . _drush_rsync_get_option($additional_options, 'include-path', '');
    $additional_options['exclude-sites'] = TRUE;
    $additional_options['exclude-other-sites-processed'] = TRUE;
  }
  // If the --exclude-files option is specified, then
  // convert that into --exclude-path='%files'.
  if (_drush_rsync_get_option($additional_options, 'exclude-files', FALSE) && !_drush_rsync_get_option($additional_options, 'exclude-files-processed', FALSE, 'process')) {
    $additional_options['exclude-path'] = '%files,' . _drush_rsync_get_option($additional_options, 'exclude-path', '');
    $additional_options['exclude-files-processed'] = TRUE;
  }
  
  // If there was no site specification given, and the
  // machine is local, then try to look
  // up an alias record for the default drush site.
  if (empty($site_alias_settings) && empty($machine)) {
    $drush_uri = drush_bootstrap_value('drush_uri', drush_get_option(array('l', 'uri'), 'default'));
    $site_alias_settings = drush_sitealias_get_record($drush_uri);
  }

  // Always add transient defaults
  _drush_sitealias_add_transient_defaults($site_alias_settings);
  
  // The $resolve_path variable is used by drush_sitealias_resolve_path_references
  // to test to see if there are any path references such as %site or %files
  // in it, so that resolution is only done if the path alias is referenced.
  // Therefore, we can concatinate without worrying too much about the structure of
  // this variable's contents.
  $include_path = _drush_rsync_get_option($additional_options, 'include-path', '');
  $exclude_path = _drush_rsync_get_option($additional_options, 'exclude-path', '');
  $resolve_path = $path . $include_path . $exclude_path;
  // Resolve path aliases such as %files, if any exist in the path
  if (!empty($resolve_path)) {
    drush_sitealias_resolve_path_references($site_alias_settings, $resolve_path);
  }
  
  if (array_key_exists('path-aliases', $site_alias_settings)) {
    $path_aliases = $site_alias_settings['path-aliases'];
  }

  // Get the 'root' setting from the alias; if it does not
  // exist, then get the root from the bootstrapped site.
  if (array_key_exists('root', $site_alias_settings)) {
    $drupal_root = $site_alias_settings['root'];
  }
  else {
    drush_bootstrap_max();
    $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  }
  if (empty($drupal_root)) {
    $drupal_root = '';
  }
  // Add a slash to the end of the drupal root, as below.
  elseif ($drupal_root[strlen($drupal_root)-1] != '/') {
    $drupal_root = $drupal_root . '/';
  }
  $full_path_aliases = $path_aliases;
  foreach ($full_path_aliases as $key => $value) {
    // Expand all relative path aliases to be based off of the Drupal root
    if (($value[0] != '/') && ($key != '%root')) {
      $full_path_aliases[$key] = $drupal_root . $value;
    }
    // Rsync is very particular about the meaning of paths that
    // end with a '/' compared to those that do not.  In order to
    // copy from !path to !path without creating an extra spurrious
    // directory, the path alias must end with a '/'.  Therefore
    // we will add a slash to the end of any alias that does not
    // already have one.
    if ($value[strlen($value)-1] != '/') {
      $full_path_aliases[$key] = $full_path_aliases[$key] . '/';
    }
  }
  
  // Fill in path aliases in the path, the include path and the exclude path.
  $path = str_replace(array_keys($full_path_aliases), array_values($full_path_aliases), $path);
  if (!empty($include_path)) {
    drush_set_option('include-path', str_replace(array_keys($path_aliases), array_values($path_aliases), $include_path));
  }
  if (!empty($exclude_path)) {
    drush_set_option('exclude-path', str_replace(array_keys($path_aliases), array_values($path_aliases), $exclude_path));
  }
  
  // The path component is just the path part of the full
  // machine:path specification (including the colon).
  $path_component = (!empty($path) ? ':' . $path : '');

  // Next make the rsync path, which includes the machine
  // and path components together.
  // First make empty paths or relative paths start from the drupal root.
  if (empty($path) || ($path[0] != '/')) {
    $path = $drupal_root . $path;
  }

  // If there is a $machine component, to the path, then
  // add it to the beginning
  if (!empty($machine)) {
    $path = $machine . ':' . $path;
  }

  $site_alias_settings['rsync-path'] = $path;
  $site_alias_settings['path-component'] = $path_component;

  return $site_alias_settings;
}

function _drush_rsync_get_option($additional_options, $option, $value = NULL, $context = NULL) {
  if (array_key_exists($option, $additional_options)) {
    return $additional_options[$option];
  }
  else {
    return drush_get_option($option, $value, $context);
  }
}

function _drush_rsync_option_exists($option, $additional_options) {
  if (array_key_exists($option, $additional_options)) {
    return TRUE;
  }
  else {
    return drush_get_option($option, FALSE);
  }
}
