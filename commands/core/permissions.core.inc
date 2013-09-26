<?php

/**
 * @file
 *   Fix file permissions in a Drupal web directory.
 *
 *   After running Git or certain Drush commands in a Drupal web directory, the
 *   file permissions can get disrupted.  This subcommand changes files
 *   attributes so as to set specific permissioons and ownership.  If not
 *   specified, some arguments will have defaults where appropriate.
 */

/**
 * Implements hook_drush_command().
 */
function perms_drush_command() {
  $items = array();

  return $items;
}

/**
 * Implementats hook_drush_help().
 */
function perms_drush_help($section) {
  switch ($section) {
    case 'drush:permissions':
      return dt("Set appropriate ownership and permissions of files and directories within a Drupal web directory.");
  }
}

/**
 * Implementats drush_hook_COMMAND_pre_validate().
 *
 * We will interpret the parameters upfront, and allow validate hooks to operate
 * on the calculated values, if desired.
 */
function drush_core_permissions_pre_validate($code_files_owner_group = '', $data_files_owner_group = '') {
  $lax = drush_get_option('lax', FALSE);
  $not_world_readable = drush_get_option('not-world-readable', FALSE);
  $not_group_writable = drush_get_option('not-group-writable', FALSE);
  if (drush_get_option('strict', FALSE)) {
    $lax = FALSE;
    $not_world_readable = TRUE;
    $not_group_writable = TRUE;
  }
  $vars = array(
    'settings-files' => '0440',
    'doc-files' =>  $lax ? '0664' : '0400',
    'code-files' => $lax ? '0664' : '0644',
    'code-dirs' =>  $lax ? '0775' : '0755',
    'data-files' => $lax ? '0666' : '0664',
    'data-dirs' =>  $lax ? '0777' : '0775',
  );
  $permission_variable_keys = array_keys($vars);
  // Process owner and group variables
  if (!drush_get_option('skip-set-owner', FALSE) && !empty($code_files_owner_group)) {
    if (!is_array($code_files_owner_group)) {
      $code_files_owner_group = explode(':', $code_files_owner_group);
    }
    if (count($code_files_owner_group) < 2) {
      $code_files_owner_group[1] = $code_files_owner_group[0];
    }
    if (empty($data_files_owner_group)) {
      $data_files_owner_group = $code_files_owner_group;
    }
    if (!is_array($data_files_owner_group)) {
      $data_files_owner_group = explode(':', $data_files_owner_group);
    }
    if (count($data_files_owner_group) < 2) {
      $data_files_owner_group[1] = $data_files_owner_group[0];
    }
    $vars['code-files-owner'] = $code_files_owner_group[0];
    $vars['code-files-group'] = $code_files_owner_group[1];
    $vars['data-files-owner'] = $data_files_owner_group[0];
    $vars['data-files-group'] = $data_files_owner_group[1];
  }
  // Allow user to give specific values for each permission via options
  foreach ($vars as $name => $value) {
    $vars[$name] = drush_get_option($name, $value);
  }
  // --strict takes precidence over user-specified permission values.
  // If --not-world-readable or --not-group-writable was specified, strip
  // permission bits off of the provided permission values.
  if ($not_world_readable || $not_group_writable) {
    foreach ($permission_variable_keys as $name) {
      $value = base_convert($vars[$name], 8, 10);
      if ($not_world_readable) {
        $value = (int)$value & base_convert('0770', 8, 10);
      }
      if ($not_group_writable) {
        $value = (int)$value & base_convert('0757', 8, 10);
      }
      $value = '0' . base_convert($value, 10, 8);
      $vars[$name] = $value;
    }
  }
  drush_set_context('DRUSH_PERMS', $vars);
}

/*
 * Implementats drush_hook_COMMAND_validate().
 */
function drush_core_permissions_validate($code_files_owner_group = '', $data_files_owner_group = '') {
  $vars = drush_get_context('DRUSH_PERMS');

  // Make sure that we've got a POSIX system.
  if (!function_exists('posix_getpwuid')) {
    return drush_set_error(
      'ERROR_NON_POSIX',
      dt('Currently, this command can only be run on a POSIX system.')
    );
  }
/*
  // We could try to automatically skip change owner commands if we are not running via sudo.
  // Perhaps erroring out is preferable.
  $name = posix_getpwuid(posix_geteuid());
  if ($name['name'] !== 'root') {
    if (!drush_get_option('skip-set-owner', FALSE) && !drush_get_option('sudo', FALSE) && !drush_get_context('DRUSH_PIPE', FALSE)) {
      drush_log(dt("You must run as root in order to change file ownership."), 'warning');
      drush_set_option('skip-set-owner', TRUE);
    }
  }
*/
  // Skip user/group validation if generating a script (perhaps to run on a different machine)
  if (!drush_get_context('DRUSH_PIPE', FALSE) && array_key_exists('code-files-owner', $vars)) {
    // Make sure that the username is valid.
    if (!posix_getpwnam($vars['code-files-owner'])) {
      return drush_set_error(
        'ERROR_USER_INVALID',
        dt('The user !user must be a valid user.', array('!user' => $vars['code-files-owner']))
      );
    }

    if (!posix_getpwnam($vars['data-files-owner'])) {
      return drush_set_error(
        'ERROR_USER_INVALID',
        dt('The user !user must be a valid user.', array('!user' => $vars['data-files-owner']))
      );
    }

    // Make sure that the group is valid.
    if (!posix_getgrnam($vars['code-files-group'])) {
      return drush_set_error(
        'ERROR_GROUP_INVALID',
        dt('The group !group must be a valid group.', array('!group' => $vars['code-files-group']))
      );
    }

    if (!posix_getgrnam($vars['data-files-group'])) {
      return drush_set_error(
        'ERROR_GROUP_INVALID',
        dt('The group !group must be a valid group.', array('!group' => $vars['data-files-group']))
      );
    }
  }

  drush_set_context('DRUSH_PERMS', $vars);
}

/**
 * Implements drush_COMMANDFILE_COMMANDNAME().
 */
function drush_core_permissions($code_files_owner_group = '', $data_files_owner_group = '') {
  $vars = drush_get_context('DRUSH_PERMS');

  // Determine the base directory to operate on
  if (!$base_dir = drush_get_option('dir')) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_ROOT);
    if (!$base_dir = drush_get_context('DRUSH_DRUPAL_ROOT')) {
      return drush_set_error(
        'ERROR_CANNOT_GET_BASE_DIR',
        dt('Cannot determine the directory to operate on. Specify a Drupal site to use, or pass in --dir=. for the current working directory.')
      );
    }
  }
  // We always work with absolute paths; adjust base dir if relative path provided
  if (!drush_is_absolute_path($base_dir)) {
    if ($base_dir == '.') {
      $base_dir = '';
    }
    $base_dir = drush_get_context('DRUSH_OLDCWD', getcwd()) . str_replace('./', '', $base_dir);
  }

  // If we are in --pipe mode, print out the script header
  $script = drush_get_context('DRUSH_PIPE', FALSE);
  if ($script) {
    $command_list['script-header'] = "#!/bin/bash";

    // If we are not in 'script' mode, replace data values with variables
    if (!drush_get_option('no-variables', FALSE)) {
      foreach($vars as $key => $value) {
        $name = str_replace("-", "_", strtoupper($key));
        $command_list['assign-' . $key] = "$name='$value'";
        $vars[$key] = array('#escape' => FALSE, 'value' => '$' . $name);
      }
    }
  }

  // Fill in variables for the files (%files, %private) and settings (%settings)
  // options, so that users can select arbitrary files and directories to handle
  // specially.
  $user_files_list = _drush_perms_get_option_list('files', '%files,%private');
  $exclude_files_list = _drush_perms_get_option_list('exclude', array());
  $settings_list = _drush_perms_get_option_list('settings', '%settings');
  $doc_pattern_list = _drush_perms_get_option_list('doc-patterns', '*.txt,quickstart.html');
  $doc_exceptions_list = _drush_perms_get_option_list('doc-exceptions', 'robots.txt');

  // respect --skip-set-owner, and only chgrp if specified.
  $owner = array_key_exists('code-files-owner', $vars) ? $vars['code-files-owner'] : '';
  $group = array_key_exists('code-files-group', $vars) ? $vars['code-files-group'] : '';
  $files_owner = array_key_exists('data-files-owner', $vars) ? $vars['data-files-owner'] : '';
  $files_group = array_key_exists('data-files-group', $vars) ? $vars['data-files-group'] : '';

  // chown everything that needs to be chown'ed
  if (!empty($owner) || !empty($group)) {
    $command_list['chown-default'] = _drush_perms_build_chown_command($base_dir, $owner, $group);
  }

  // Run chmod on all directories and files at the root.
  $command_list['chmod-code-dirs'] = _drush_perms_build_chmod_command($base_dir, 'd', $vars['code-dirs']);
  $command_list['chmod-code-files'] = _drush_perms_build_chmod_command($base_dir, 'f', $vars['code-files']);

  // By default, we'll avoid running chown and chmod commands
  // on %files and %private, since they will have their own
  // user / group settings.  If, by chance, the modifications
  // for %files and %private are the same, then we'll leave these
  // directories in the main operation.
  $owner_differs = (($owner != $files_owner) || ($group != $files_group));
  $dir_perms_differ = $vars['code-dirs'] != $vars['data-dirs'];
  $file_perms_differ = $vars['code-files'] != $vars['data-files'];
  foreach($user_files_list as $path_key) {
    $absolute_path = _drush_perms_evaluate_path($base_dir, $path_key);
    if ($absolute_path && !_drush_perms_is_excluded($exclude_files_list, $base_dir, $absolute_path)) {
      $relative_path = _drush_perms_relative_path($base_dir, $absolute_path);
      // We need to build a separate chown command if the file owners are different
      // than the default owner, or if the file path is outside the base_dir
      if ($owner_differs || ($relative_path === FALSE) && ((!empty($files_owner)) || (!empty($files_group)))) {
        $command_list['chown-' . $path_key] = _drush_perms_build_chown_command($absolute_path, $files_owner, $files_group);
      }
      // We need to exclude the file path from the default path if the
      // owners are different and the file path is inside the base_dir
      if ($owner_differs && ($relative_path !== FALSE)) {
        $command_list['chown-default']['!prune']['terms']['exclude-' . $path_key] = array(
          'tmpl' => '-path ^path',
          '^path' => $absolute_path,
        );
      }
      // Similarly, make our own chmod commands for user dirs and files if needed
      if ($dir_perms_differ || ($relative_path === FALSE)) {
        $command_list['data-dirs'] = _drush_perms_build_chmod_command($absolute_path, 'd', $vars['data-dirs']);
      }
      if ($dir_perms_differ && ($relative_path !== FALSE)) {
        $command_list['chmod-code-dirs']['!prune']['terms']['exclude-' . $path_key] = array(
          'tmpl' => '-path ^path',
          '^path' => $absolute_path,
        );
      }
      if ($file_perms_differ || ($relative_path === FALSE)) {
        $command_list['data-files'] = _drush_perms_build_chmod_command($absolute_path, 'f', $vars['data-files']);
      }
      if ($file_perms_differ && ($relative_path !== FALSE)) {
        $command_list['chmod-code-files']['!prune']['terms']['exclude-' . $path_key] = array(
          'tmpl' => '-path ^path',
          '^path' => $absolute_path,
        );
      }
    }
  }

  // Exclude the directories specified via --exclude in every command
  // that is operating from $base_dir
  foreach($exclude_files_list as $path_key) {
    $absolute_path = _drush_perms_evaluate_path($base_dir, $path_key);
    if ($absolute_path) {
      foreach (array_keys($command_list) as $key) {
        if (is_array($command_list) && isset($command_list[$key]['!tests'])) {
          $path_base_dir = $command_list[$key]['^base_dir'];
          $relative_path = _drush_perms_relative_path($path_base_dir, $absolute_path);
          if (!empty($relative_path)) {
            $command_list[$key]['!prune']['terms']['exclude-' . $path_key] = array(
              'tmpl' => '-path ^path',
              '^path' => $absolute_path,
            );
          }
        }
      }
    }
  }

  // Next, process settings.php (or the user-supplied substitutes for same)
  foreach($settings_list as $settings_key) {
    $absolute_path = _drush_perms_evaluate_path($base_dir, $settings_key);
    if ($absolute_path) {
      $relative_path = _drush_perms_relative_path($base_dir, $absolute_path);
      if (!empty($relative_path)) {
        // Build a chmod command for settings.  Settings are always included
        // in the chown command 'chown-default'.
        $command_list['file-' . $settings_key] = array(
          'tmpl' => 'chmod !permissions !file',
          '!permissions' => $vars['settings-files'],
          '!file' => $absolute_path,
        );
        // Exclude settings.php from the 'find' command that sets code file permissions
        $command_list['chmod-code-files']['!tests']['exclude-' . $settings_key] = array(
          'tmpl' => '\\! -path ^file',
          '^file' => $relative_path,
        );
      }
    }
  }

  // If the doc files have different permissions, then exclude them from
  // the 'chmod-code-files' command and gen up our own chmod that operates only
  // on the specified file patterns at the Drupal root.
  $doc_perms_differ = $vars['code-files'] != $vars['doc-files'];
  if ($doc_perms_differ) {
    $command_list['doc-files'] = _drush_perms_build_docs_chmod_command($base_dir, FALSE, $doc_pattern_list, $doc_exceptions_list, $vars['doc-files']);
    $command_list['not-doc-files'] = _drush_perms_build_docs_chmod_command($base_dir, TRUE, $doc_pattern_list, $doc_exceptions_list, $vars['code-files']);
    // We skip the files at the immediate root, as these files are covered by the commands we built above.
    // Adding a `-mindepth 2` term interferes with -prune, so we instead use a pattern of '*/*' to
    // mean "path contains a slash" (that is, path is not a file at the root). We also must prepend
    // with "$base_dir/".
    $command_list['chmod-code-files']['!tests']['not-at-root'] = array(
      'tmpl' => '-path ^path',
      '^path' => $base_dir . '/*/*',
    );
  }

  // In sudo mode, add a 'sudo' to each command
  $sudo = drush_get_option('sudo', FALSE);
  if ($sudo) {
    foreach($command_list as $key => $command_record) {
      // If --sudo=all was specified, then add a 'sudo' to every command.
      // Otherwise, only add 'sudo' to commands flagged as '#privileged'
      if (is_array($command_record) && isset($command_record['!command']) && (($sudo === 'all') || (array_key_exists('#privileged', $command_record)))) {
        $command_list[$key]['!command']['tmpl'] = 'sudo ' . $command_list[$key]['!command']['tmpl'];
      }
    }
  }
  // If in audit mode, convert the commands to 'echo';
  $audit = drush_get_option('audit', FALSE);
  if ($audit) {
    foreach($command_list as $key => $command_record) {
      if (is_array($command_record) && isset($command_record['!command'])) {
        $command_list[$key]['!command']['tmpl'] = 'echo ' . $command_list[$key]['!command']['tmpl'];
      }
    }
  }

  // Print or execute each command
  foreach($command_list as $key => $command_record) {
    $cmd = _drush_perms_render_command($command_record);
    if ($script) {
      drush_print_pipe($cmd . "\n");
    }
    elseif (!empty($cmd) && ($cmd[0] != '#')) {
      $result = drush_op_system($cmd);
      if ($result > 0) {
        return FALSE;
      }
    }
  }
  if (!$script && !$audit) {
    drush_log(dt("Permissions change complete."), 'success');
  }
  return TRUE;
}

function _drush_perms_relative_path($base_path, $test_path) {
  $result = $test_path;
  if (drush_is_absolute_path($test_path)) {
    if ($test_path == $base_path) {
      $result = './';
    }
    elseif (substr($test_path, 0, strlen($base_path)) == $base_path) {
      if (($test_path[strlen($base_path)] == '/') || ($test_path[strlen($base_path)] == DIRECTORY_SEPARATOR)) {
        $result = substr($test_path, strlen($base_path) + 1);
      }
      else {
        $result = FALSE;
      }
    }
    else {
      $result = FALSE;
    }
  }
  return $result;
}

function _drush_perms_is_excluded($exclude_files_list, $base_dir, $test_path) {
  foreach ($exclude_files_list as $excluded) {
    $excluded_absolute = _drush_perms_absolute_path($base_dir, $excluded);
    $relative_path = _drush_perms_relative_path($excluded_absolute, $test_path);
    if ($relative_path !== FALSE) {
      return TRUE;
    }
  }

  return FALSE;
}

function _drush_perms_absolute_path($base_path, $test_path) {
  if (drush_is_absolute_path($test_path)) {
    return $test_path;
  }
  else {
    return $base_path . DIRECTORY_SEPARATOR . $test_path;
  }
}

function _drush_perms_evaluate_path($base_dir, $path) {
  if ($path[0] == '%') {
    $path = _drush_core_directory($path, 'path', TRUE);
    return (strpos($path, '%') === FALSE) ? $path : FALSE;
  }
  else {
    return _drush_perms_absolute_path($base_dir, $path);
  }
}

/**
 * Generate a command record that will chown files via 'find' and 'exec'
 */
function _drush_perms_build_chown_command($base_dir, $owner, $group) {
  $result = array();
  $chmod_template = "!cmd ^arg --";
  $test_owner_template = "\\( \\! -group ^group -o \\! -user ^owner \\)";
  $privileged = TRUE;
  $command = 'chown';
  $arg = $owner;
  $kind = '-owner';
  if (empty($owner) || empty($group)) {
    $template = "! !kind ^arg";
  }
  if (empty($owner)) {
    $command = 'chgrp';
    $arg = $group;
    $kind = '-group';
    $privileged = FALSE;
  }
  elseif (!empty($group)) {
    $chmod_template = "!cmd ^owner:^group --";
  }
  if (!empty($arg)) {
    $result = array(
      'tmpl' => "find ^base_dir !options !prune \( !tests -print0 \) | xargs -0r !command",
      // ^base_dir will be shell escaped before being inserted into tmpl
      '^base_dir' => $base_dir,
      '!options' => array(),
      // !prune might have terms added to it later by caller
      '!prune' => array(
        'terms' => array(
          '#alter' => array('or-terms', 'parenthesize'),
        ),
        // #alter condition-not-empty will cause this item to be skipped if
        // none of the preceding terms generated any output.
        'prune' => array(
          '#alter' => array('condition-not-empty'),
          'tmpl' => '-prune -o',
        ),
      ),
      // !tests will be replaced by the space-separated options list provided
      '!tests' => array(
        'test-owner' => array(
          'tmpl' => $test_owner_template,
          '!kind' => $kind,
          '^arg' => $arg,
          '^owner' => $owner,
          '^group' => $group,
        ),
      ),
      // !command will be recursively evaluated and inserted into 'tmpl'.
      '!command' => array(
        'tmpl' => $chmod_template,
        '!cmd' => $command,
        '^arg' => $arg,
        '^owner' => $owner,
        '^group' => $group,
      )
    );
    if ($privileged) {
      // Mark #privileged to indicate the superuser privileges
      // are always necessary to run this command (changing owner).
      $result['#privileged'] = TRUE;
    }
  }
  return $result;
}

/**
 * Generate a command record that will chmod files via 'find' and 'exec'
 *
 * Ex:
 *   find /path -type f ! -perm 0755 -print0 | xargs -0r chmod 0755 --
 */
function _drush_perms_build_chmod_command($base_dir, $type, $permissions) {
  return array(
    'tmpl' => "find ^base_dir !options !prune \( !tests -print0 \) | xargs -0r !command",
    // ^base_dir will be shell escaped before being inserted into tmpl
    '^base_dir' => $base_dir,
    '!options' => array(),
    // !prune might have terms added to it later by caller
    '!prune' => array(
      'terms' => array(
        '#alter' => array('or-terms', 'parenthesize'),
      ),
      // #alter condition-not-empty will cause this item to be skipped if
      // none of the preceding terms generated any output.
      'prune' => array(
        '#alter' => array('condition-not-empty'),
        'tmpl' => '-prune -o',
      ),
    ),
    // !tests will be replaced by the space-separated options list provided
    '!tests' => array(
      'type' => array(
        'tmpl' => '-type !type',
        '!type' => $type,
      ),
      'perm' => array(
        'tmpl' => '\\! -perm !permissions',
        '!permissions' => $permissions,
      ),
    ),
    // !command will be recursively evaluated and inserted into 'tmpl'.
    '!command' => array(
      'tmpl' => "chmod !permissions --",
      '!permissions' => $permissions,
    )
  );
}

/**
 * Generate a command record that will chmod documentation files via 'find' and 'exec'
 *
 * Ex:
 *   find /path -maxdepth 1 -type f ! ( -path '*.txt' -o -path 'quickstart.html' ) -print0 | xargs -0r chmod 0400 --
 */
function _drush_perms_build_docs_chmod_command($base_dir, $not, $doc_pattern_list, $doc_exceptions_list, $permissions) {
  $result = _drush_perms_build_chmod_command($base_dir, 'f', $permissions);
  $result['!options']['maxdepth'] = '-maxdepth 1';
  $doc_exceptions = array('#alter' => array('or-terms', 'parenthesize'));
  foreach ($doc_exceptions_list as $key => $item) {
    $doc_exceptions['doc-' . $item] = array(
      'tmpl' => '-path ^item',
      '^item' => $base_dir . '/' . $item,
    );
  }
  if ((count($doc_exceptions_list) > 0) && ($not)) {
    $result['!tests']['path-start-paren'] = '\(';
    $result['!tests']['doc-exceptions-list'] = $doc_exceptions;
    $result['!tests']['exceptions-list-or'] = '-o';
  }
  if (count($doc_pattern_list) > 0) {
    if ($not) {
      $result['!tests']['pattern-list-not'] = '\\!';
    }
    $result['!tests']['doc-pattern-list']['#alter'] = array('or-terms', 'parenthesize');
    foreach ($doc_pattern_list as $key => $pattern) {
      if ($pattern[0] != '*') {
        $pattern = $base_dir . '/' . $pattern;
      }
      $result['!tests']['doc-pattern-list']['doc-' . $pattern] = array(
        'tmpl' => '-path ^pattern',
        '^pattern' => $pattern,
      );
    }
  }
  if ((count($doc_exceptions_list) > 0) && (!$not)) {
    $result['!tests']['exceptions-list-not'] = '\\!';
    $result['!tests']['doc-exceptions-list'] = $doc_exceptions;
  }
  if ((count($doc_exceptions_list) > 0) && ($not)) {
    $result['!tests']['path-end-paren'] = '\)';
  }
  return $result;
}

/**
 * Convert a command record into an executable command string.
 *
 * @param mixed $command_list
 *   The command to render. Processing depends on the structure of the data.
 *     string - returns the string itself
 *     array
 *       'tmpl' => 'command template with !replacements',
 *       '!var' => array(...), // nested command record
 *       '^var' => array(...), // data is escaped for shell
 *     array
 *       'id1' => array(...), // nested command record
 *       'id2' => array(...), // nested command record
 *
 * Command records with a 'tmpl' are expanded much as the t() function
 * is, with the data from the other elements in the array also being
 * evaluated and used as substitutions in the template.
 *
 * If no template is provided, then every item in the array is evaluated
 * as a command template, and all are concatinated together, separated by
 * spaces, and returned.  The id is ignored, except for the fact that the
 * value will be escaped if the id begins with "^".
 */
function _drush_perms_render_command($command_or_command_list, $replacements = array()) {
  $result = '';
  if (is_array($command_or_command_list)) {
    $data = array();
    if (array_key_exists('tmpl', $command_or_command_list)) {
      $tmpl = $command_or_command_list['tmpl'];
      unset($command_or_command_list['tmpl']);
    }
    foreach ($command_or_command_list as $key => $info) {
      if ($key[0] != '#') {
        if (is_array($info) && array_key_exists('#alter', $info)) {
          foreach ($info['#alter'] as $fn) {
            $fn_name = '_drush_perms_pre_render_' . str_replace('-', '_', $fn);
            if (function_exists($fn_name)) {
              $fn_name($info);
            }
          }
        }
        $escape = (is_array($info) && array_key_exists('#escape', $info)) ? $info['#escape'] : 'default';
        $value = _drush_perms_render_command($info, $replacements);
        if ($escape == 'default') {
          $escape = ($key[0] == '^');
        }
        if ($escape) {
          $value = drush_escapeshellarg($value);
        }
        if (is_array($info) && array_key_exists('#alter', $info)) {
          foreach ($info['#alter'] as $fn) {
            $fn_name = '_drush_perms_post_render_' . str_replace('-', '_', $fn);
            if (function_exists($fn_name)) {
              $value = $fn_name($info, $value, $data);
            }
          }
        }
        if (isset($value)) {
          $data[$key] = $value;
        }
      }
    }
    // First array form: array with 'tmpl' and replacements
    if (isset($tmpl)) {
      $result = str_replace(array_keys($data), array_values($data), $tmpl);
    }
    // Second array form: array of command records to be concatinated
    else {
      $result = implode(" ", $data);
    }
  }
  else {
    // String form: just return the data unmodified.
    $result = $command_or_command_list;
    // Allow simple results to be replaced by variables where appropriate.
    $result = str_replace(array_keys($replacements), array_values($replacements), $result);
  }
  return $result;
}

/**
 * If there is no data in '$data' yet, then erase the current term;
 * otherwise, allow the current term to be processed and added to
 * the result.
 */
function _drush_perms_post_render_condition_not_empty($info, $value, $data) {
  if (_drush_perms_count_terms($data) == 0) {
    return NULL;
  }
  else {
    return $value;
  }
}

/**
 * If there is more than one term in the provided set of terms,
 * then add an "or" term ("-o") between each one.
 */
function _drush_perms_pre_render_or_terms(&$info) {
  $added_term = FALSE;
  $i = array();
  foreach ($info as $key => $value) {
    if ($key[0] != '#') {
      if ($added_term) {
        $i['or-' . $key] = '-o';
      }
      $added_term = TRUE;
    }
    $i[$key] = $value;
  }
  if ($added_term) {
    $info = $i;
  }
}

/**
 * If there is more than one term in the provided set of terms,
 * then add parenthesis around all of the terms.
 */
function _drush_perms_pre_render_parenthesize(&$info) {
  if (_drush_perms_count_terms($info) > 1) {
    $i = array_reverse($info, TRUE);
    $i['begin_paren'] = '\(';
    $i = array_reverse($i, TRUE);
    $i['end_paren'] = '\)';
    $info = $i;
  }
}

/**
 * Count the number of 'terms' in the provided array.
 * Ignore processing directives -- keys that begin with '#'.
 */
function _drush_perms_count_terms($info) {
  $count = 0;
  if (is_array($info)) {
    foreach ($info as $k => $v) {
      if (($k[0] != '#') && (!empty($v))) {
        $count = $count + 1;
      }
    }
  }
  return $count;
}

/**
 * We would prefer drush_get_option_list to return an empty
 * array if the user provides an empty string.  By default,
 * it returns an array with a single empty item in this instance.
 */
function _drush_perms_get_option_list($key, $default) {
  $result = drush_get_option_list($key, $default);
  if ($result == array(0 => '')) {
    return array();
  }
  else {
    return $result;
  }
}
