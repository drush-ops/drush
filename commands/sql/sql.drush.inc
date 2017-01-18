<?php

/**
 * @file
 * Drush sql commands
 */

/**
 * Implements hook_drush_help_alter().
 */
function sql_drush_help_alter(&$command) {
  // Drupal 7+ only options.
  if (drush_drupal_major_version() >= 7) {
    if ($command['commandfile'] == 'sql') {
      unset($command['options']['target']['hidden']);
    }
  }
}

/**
 * Safely bootstrap Drupal to the point where we can
 * access the database configuration.
 */
function drush_sql_bootstrap_database_configuration() {
  // Under Drupal 7, if the database is configured but empty, then
  // DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION will throw an exception.
  // If this happens, we'll just catch it and continue.
  // TODO:  Fix this in the bootstrap, per http://drupal.org/node/1996004
  try {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }
  catch (Exception $e) {
  }
}

/**
 * Check whether further bootstrap is needed. If so, do it.
 */
function drush_sql_bootstrap_further() {
  if (!drush_get_option(array('db-url', 'db-spec'))) {
    drush_sql_bootstrap_database_configuration();
  }
}

// @todo Remove once sql-sync and archive-dump are using \Drush\Commands\OptionsCommands::optionsetTableSelection
function drush_sql_get_table_selection_options() {
  return array(
    'skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
    'structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
    'tables-key' => 'A key in the $tables array. Optional.',
    'skip-tables-list' => 'A comma-separated list of tables to exclude completely. Optional.',
    'structure-tables-list' => 'A comma-separated list of tables to include for structure, but not data. Optional.',
    'tables-list' => 'A comma-separated list of tables to transfer. Optional.',
  );
}

/**
 * Call from a pre-sql-sync hook to register an sql
 * query to be executed in the post-sql-sync hook.
 * @see drush_sql_pre_sql_sync() and @see drush_sql_post_sql_sync().
 *
 * @param $id
 *   String containing an identifier representing this
 *   operation.  This id is not actually used at the
 *   moment, it is just used to fufill the contract
 *   of drush contexts.
 * @param $message
 *   String with the confirmation message that describes
 *   to the user what the post-sync operation is going
 *   to do.  This confirmation message is printed out
 *   just before the user is asked whether or not the
 *   sql-sync operation should be continued.
 * @param $query
 *   String containing the sql query to execute.  If no
 *   query is provided, then the confirmation message will
 *   be displayed to the user, but no action will be taken
 *   in the post-sync hook.  This is useful for drush modules
 *   that wish to provide their own post-sync hooks to fix
 *   up the target database in other ways (e.g. through
 *   Drupal APIs).
 */
function drush_sql_register_post_sync_op($id, $message, $query = NULL) {
  $options = drush_get_context('post-sync-ops');

  $options[$id] = array('message' => $message, 'query' => $query);

  drush_set_context('post-sync-ops', $options);
}

/**
 * Builds a confirmation message for all post-sync operations.
 *
 * @return string
 *   All post-sync operation messages concatenated together.
 */
function _drush_sql_get_post_sync_messages() {
  $messages = '';
  $operations = drush_get_context('post-sync-ops');
  if (!empty($operations)) {
    $messages = dt('The following operations will be done on the target database:') . "\n";

    $bullets = array_column($operations, 'message');
    $messages .= " * " . implode("\n * ", $bullets) . "\n";
  }
  return $messages;
}

/**
 * Wrapper for drush_get_class; instantiates an driver-specific instance
 * of SqlBase class.
 *
 * @param array $db_spec
 *   If known, specify a $db_spec that the class can operate with.
 *
 * @throws \Drush\Sql\SqlException
 *
 * @return Drush\Sql\SqlBase
 */
function drush_sql_get_class($db_spec = NULL) {
  $database = drush_get_option('database', 'default');
  $target = drush_get_option('target', 'default');

  // Try a few times to quickly get $db_spec.
  if (!empty($db_spec)) {
    if (!empty($db_spec['driver'])) {
      return drush_get_class(array('Drush\Sql\Sql', 'Drupal\Driver\Database\\' .  $db_spec['driver'] . '\Drush'), array($db_spec), array($db_spec['driver']));
    }
  }
  elseif ($url = drush_get_option('db-url')) {
    $url =  is_array($url) ? $url[$database] : $url;
    $db_spec = drush_convert_db_from_db_url($url);
    $db_spec['db_prefix'] = drush_get_option('db-prefix');
    return drush_sql_get_class($db_spec);
  }
  elseif (($databases = drush_get_option('databases')) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
    $db_spec = $databases[$database][$target];
    return drush_sql_get_class($db_spec);
  }
  else {
    // No parameter or options provided. Determine $db_spec ourselves.
    if ($sqlVersion = drush_sql_get_version()) {
      if ($db_spec = $sqlVersion->get_db_spec()) {
        return drush_sql_get_class($db_spec);
      }
    }
  }

  throw new \Drush\Sql\SqlException('Unable to find a matching SQL Class. Drush cannot find your database connection details.');
}

/**
 * Wrapper for drush_get_class; instantiates a Drupal version-specific instance
 * of SqlVersion class.
 *
 * @return Drush\Sql\SqlVersion
 */
function drush_sql_get_version() {
  return drush_get_class('Drush\Sql\Sql', array(), array(drush_drupal_major_version())) ?: NULL;
}

/**
 * Implements hook_drush_sql_sync_sanitize().
 *
 * Sanitize usernames, passwords, and sessions when the --sanitize option is used.
 * It is also an example of how to write a database sanitizer for sql sync.
 *
 * To write your own sync hook function, define mymodule_drush_sql_sync_sanitize()
 * in mymodule.drush.inc and follow the form of this function to add your own
 * database sanitization operations via the register post-sync op function;
 * @see drush_sql_register_post_sync_op().  This is the only thing that the
 * sync hook function needs to do; sql-sync takes care of the rest.
 *
 * The function below has a lot of logic to process user preferences and
 * generate the correct SQL regardless of whether Postgres, Mysql,
 * Drupal 6/7/8 is in use.  A simpler sanitize function that
 * always used default values and only worked with Drupal 6 + mysql
 * appears in the drush.api.php.  @see hook_drush_sql_sync_sanitize().
 */
function sql_drush_sql_sync_sanitize($site) {
  $site_settings = drush_sitealias_get_record($site);
  $databases = sitealias_get_databases_from_record($site_settings);
  $major_version = drush_drupal_major_version();
  $wrap_table_name = (bool) drush_get_option('db-prefix');
  $user_table_updates = array();
  $message_list = array();

  // Sanitize passwords.
  $newpassword = drush_get_option(array('sanitize-password', 'destination-sanitize-password'), 'password');
  if ($newpassword != 'no' && $newpassword !== 0) {
    $pw_op = "";

    // In Drupal 6, passwords are hashed via the MD5 algorithm.
    if ($major_version == 6) {
      $pw_op = "MD5('$newpassword')";
    }
    // In Drupal 7, passwords are hashed via a more complex algorithm,
    // available via the user_hash_password function.
    elseif ($major_version == 7) {
      $core = DRUSH_DRUPAL_CORE;
      include_once $core . '/includes/password.inc';
      include_once $core . '/includes/bootstrap.inc';
      $hash = user_hash_password($newpassword);
      $pw_op = "'$hash'";
    }
    else {
      // D8+. Mimic Drupal's /scripts/password-hash.sh
      drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
      $password_hasher = \Drupal::service('password');
      $hash = $password_hasher->hash($newpassword);
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
      elseif ($db_driver == 'mssql') {
        $email_map = array('%uid' => "' + uid + '", '%mail' => "' + replace(mail, '@', '_') + '", '%name' => "' + replace(name, ' ', '_') + '");
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
    $table = $major_version >= 8 ? 'users_field_data' : 'users';
    if ($wrap_table_name) {
      $table = "{{$table}}";
    }
    $sanitize_query = "UPDATE {$table} SET " . implode(', ', $user_table_updates) . " WHERE uid > 0;";
    drush_sql_register_post_sync_op('user-email', dt('Reset !message in !table table', array('!message' => implode(' and ', $message_list), '!table' => $table)), $sanitize_query);
  }

  $sanitizer = new \Drush\Commands\core\SanitizeCommands();
  $sanitizer->doSanitize($major_version);
}
