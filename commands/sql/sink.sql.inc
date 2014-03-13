<?php

/*
 * Notes:
 * - Drush is required on source and dest (except with --no-dump)
 * - Source and Dest can both be remote. Convenient.
 * - No special handling for multiple targets. Very rare.
 * - We always do 'deferred sanitization'. Would be easy to move this earlier if desired.
 * - A bit tricky to know where to put your drushrc/alias customizations. Can be local, $source, or $destination.
 * - Stop optimizing for efficient rsync.
 *   -- Always use gzip during sql-dump. Don't default to --ordered-dump.
 *   -- Rsync destination is always a temp dir
 *   -- No 24 hour freshness check for a team of devs.
 * - Can we now simplify anything in sitealias.inc or backend.inc?
 *
 * Todo:
 *  - Cleanup temp file on $dest. Verify temp file is deleted on $source.
 *  - Make test pass.
 */

/*
 * A command validate callback.
 */
function drush_sql_sink_validate($source, $destination) {
  // Get destination info for confirmation prompt.
  $source_settings = drush_sitealias_overlay_options(drush_sitealias_get_record($source), 'source-');
  $destination_settings = drush_sitealias_overlay_options(drush_sitealias_get_record($destination), 'target-');
  $source_db_url = drush_sitealias_get_db_spec($source_settings, FALSE, 'source-');
  $target_db_url = drush_sitealias_get_db_spec($destination_settings, FALSE, 'target-');
  $txt_source = (isset($source_db_url['remote-host']) ? $source_db_url['remote-host'] . '/' : '') . $source_db_url['database'];
  $txt_destination = (isset($target_db_url['remote-host']) ? $target_db_url['remote-host'] . '/' : '') . $target_db_url['database'];
  // Validate.
  if (empty($source_db_url)) {
    if (empty($source_settings)) {
      return drush_set_error('DRUSH_ALIAS_NOT_FOUND', dt('Error: no alias record could be found for source !source', array('!source' => $source)));
    }
    return drush_set_error('DRUSH_DATABASE_NOT_FOUND', dt('Error: no database record could be found for source !source', array('!source' => $source)));
  }
  if (empty($target_db_url)) {
    if (empty($destination_settings)) {
      return drush_set_error('DRUSH_ALIAS_NOT_FOUND', dt('Error: no alias record could be found for target !destination', array('!destination' => $destination)));
    }
    return drush_set_error('DRUSH_DATABASE_NOT_FOUND', dt('Error: no database record could be found for target !destination', array('!destination' => $destination)));
  }
  if (isset($source_db_url['remote-host']) && isset($target_db_url['remote-host']) && ($source_db_url['remote-host'] == $target_db_url['remote-host']) && ($source_db_url['database'] == $target_db_url['database']) && !drush_get_context('DRUSH_SIMULATE')) {
    return drush_set_error('DRUSH_SAME_DATABASE', dt('Source and target databases are the same; please sync to a different target.'));
  }

  drush_print(dt("You will destroy data in !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
  // @todo show sanitization messages as per $destination.
  // $messages = _drush_sql_get_post_sync_messages(); // on dest.
  if (!drush_confirm(dt('Do you really want to continue?'))) {
    return drush_user_abort();
  }
}

/*
 * A command callback.
 */
function drush_sql_sink($source, $destination) {
  $global_options = drush_redispatch_get_options() + array(
     'strict' => 0,
  );

  // Create destination DB if needed.
  if (drush_get_option('create-db')) {
    $return = drush_invoke_process($destination, 'sql-create', array(), $global_options);
    if ($return['error_status']) {
      return drush_set_error('sql-create failed.');
    }
  }

  // Perform sql-dump on source unless told othrwise.
  $options = $global_options + array(
      'gzip' => TRUE,
      'result-file' => TRUE,
      // 'structure-tables-list' => 'cache*', // Do we want to default to this?
    );
  if (!drush_get_option('no-dump')) {
    $return = drush_invoke_process($source, 'sql-dump', array(), $options);
    if ($return['error_status']) {
      return drush_set_error('sql-dump failed.');
    }
    else {
      $source_dump_path = $return['object'];
    }
  }

  $tmp = '/tmp'; // Our fallback plan.
  $return = drush_invoke_process($destination, 'php-eval', array('return drush_find_tmp();'), array(), array('integrate' => FALSE, 'override-simulated' => TRUE));
  if (!$return['error_status']) {
    $tmp = $return['object'];
  }

  // Rsync file from source to destination.
  // Don't send redispatch options to rsync as they get appended after core-rsync with sad outcome.
  if (!drush_get_option('no-dump')) {
    // Cleanup if we made the dump file.
    $options += array('remove-source-files' => TRUE);
  }
  $destination_dump_path = $tmp . '/' . basename($source_dump_path);
  $return = drush_invoke_process($destination, 'core-rsync', array("$source:$source_dump_path", "@self:$destination_dump_path"));
  if ($return['error_status']) {
    return drush_set_error('core-rsync failed.');
  }

  // Import file into destination.
  // @todo perhaps call drush_register_file_for_deletion($destination_dump_path) on $destination.
  $options = $global_options + array('file' => $destination_dump_path);
  $return = drush_invoke_process($destination, 'sql-query', array(), $options);
  if ($return['error_status']) {
    // An error was already logged.
    return FALSE;
  }

  // Run Sanitize if needed.
  $options = $global_options + array();
  if (drush_get_option('sanitize')) {
    $return = drush_invoke_process($destination, 'sql-sanitize', array(), $options);
    if ($return['error_status']) {
      return drush_set_error('sql-sanitize failed.');
    }
  }
}

/**
 * Sql sync sanitization function.  This hook function will sanitize usernames,
 * passwords, and sessions when the --sanitize option is used.  It is
 * also an example of how to write a database sanitizer for sql sync.
 *
 * To write your own sync hook function, define mymodule_drush_sql_sync_sanitize()
 * and follow the form of this function to add your own database
 * sanitization operations via the register post-sync op function;
 * @see drush_sql_register_post_sync_op().  This is the only thing that the
 * sync hook function needs to do; sql-sync takes care of the rest.
 *
 * The function below has a lot of logic to process user preferences and
 * generate the correct SQL regardless of whether Postgres, Mysql,
 * Drupal 6 or Drupal 7 is in use.  A simpler sanitize function that
 * always used default values and only worked with Drupal 6 + mysql
 * appears in the drush.api.php.  @see hook_drush_sql_sync_sanitize().
 */
function sink_drush_sql_sync_sanitize($site) {
  $site_settings = drush_sitealias_get_record($site);
  $databases = sitealias_get_databases_from_record($site_settings);
  $prefix = $databases['default']['default']['prefix'];
  $prefix = isset($databases['default']['default']['prefix']) ? $databases['default']['default']['prefix'] : '';
  $user_table_updates = array();
  $message_list = array();

  // Sanitize passwords.
  $newpassword = drush_get_option(array('sanitize-password', 'destination-sanitize-password'), 'password');
  if ($newpassword != 'no' && $newpassword !== 0) {
    $major_version = drush_drupal_major_version();
    $pw_op = "";

    // In Drupal 6, passwords are hashed via the MD5 algorithm.
    if ($major_version == 6) {
      $pw_op = "MD5('$newpassword')";
    }
    // In Drupal 7, passwords are hashed via a more complex algorithm,
    // available via the user_hash_password function.
    elseif ($major_version >= 7) {
      $core = DRUSH_DRUPAL_CORE;
      include_once $core . '/includes/password.inc';
      include_once $core . '/includes/bootstrap.inc';
      $hash = user_hash_password($newpassword);
      $pw_op = "'$hash'";
    }
    if (!empty($pw_op)) {
      $user_table_updates[] = "pass = $pw_op";
      $message_list[] =  "passwords";
    }
  }

  // Sanitize email addresses.
  $newemail = drush_get_option(array('sanitize-email', 'destination-sanitize-email'), 'user+%uid@localhost.localdomain');
  if ($newemail != 'no' && $newemail !== 0) {
    if (strpos($newemail, '%') !== FALSE) {
      // We need a different sanitization query for Postgres and Mysql.

      $db_driver = $databases['default']['default']['driver'];
      if ($db_driver == 'pgsql') {
        $email_map = array('%uid' => "' || uid || '", '%mail' => "' || replace(mail, '@', '_') || '", '%name' => "' || replace(name, ' ', '_') || '");
        $newmail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $newemail) . "'";
      }
      else {
        $email_map = array('%uid' => "', uid, '", '%mail' => "', replace(mail, '@', '_'), '", '%name' => "', replace(name, ' ', '_'), '");
        $newmail =  "concat('" . str_replace(array_keys($email_map), array_values($email_map), $newemail) . "')";
      }
      $user_table_updates[] = "mail = $newmail, init = $newmail";
    }
    else {
      $user_table_updates[] = "mail = '$newemail', init = '$newemail'";
    }
    $message_list[] = 'email addresses';
  }

  if (!empty($user_table_updates)) {
    $sanitize_query = "UPDATE {$prefix}users SET " . implode(', ', $user_table_updates) . " WHERE uid > 0;";
    drush_sql_register_post_sync_op('user-email', dt('Reset !message in user table', array('!message' => implode(' and ', $message_list))), $sanitize_query);
  }

  // Seems quite portable (SQLite?) - http://en.wikipedia.org/wiki/Truncate_(SQL)
  $sql_sessions = "TRUNCATE TABLE {$prefix}sessions;";
  drush_sql_register_post_sync_op('sessions', dt('Truncate Drupal\'s sessions table'), $sql_sessions);
}
