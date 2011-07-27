<?php

require_once DRUSH_BASE_PATH . '/commands/core/rsync.core.inc';

/**
 * Sql sync init function.  Bootstrap either the source or the
 * destination site.  At least one of the sites
 * must be local for this to work; if both sites are remote,
 * then it clearly will not be possible to bootstrap to
 * either of them.  If both are local, the source site is preferred.
 */
function drush_sql_sync_init($source = NULL, $destination = NULL) {
  // Preflight destination in case it defines the alias used by the source
  _drush_sitealias_get_record($destination);

  // After preflight, get source and destination settings
  $source_settings = drush_sitealias_get_record($source);
  $destination_settings = drush_sitealias_get_record($destination);

  // Insure that we have database records for the source and destination
  // alias records.  sitealias_get_databases_from_record will cache the
  // database info inside the alias records, and drush_sitealias_set_alias_context
  // will copy the database record into the 'alias' context.  We do not
  // actually use the databases record at this time.
  sitealias_get_databases_from_record($source_settings);
  sitealias_get_databases_from_record($destination_settings);
  
  // Bootstrap to the source sites being sync'ed if it is local.  
  // This allows modules enabled in the site to participate in the 
  // sql-sync hook functions (e.g. to add sanitization operations, etc.).
  // If the source is remote and the destination is local, then we
  // will determine the sanitization operations after the database
  // has been copied.
  if (!drush_get_option('deferred-sanitization', FALSE) && drush_get_option(array('sanitize', 'destination-sanitize'), FALSE)) {
    $bootstrapped = drush_bootstrap_max_to_sitealias($source_settings);
    if ($bootstrapped) {
      drush_command_invoke_all('drush_sql_sync_sanitize', $source);
    }
    else {
      drush_set_option('deferred-sanitization', TRUE);
    }
  }
  
  // By default, sql-sync will do an ordered dump.
  // Set --no-ordered-dump to override.
  if (!drush_get_option('no-ordered-dump', FALSE)) {
    drush_set_option('ordered-dump', TRUE);
  }
  
  return TRUE;
}

/**
 * Sql sync sanitization function.  This hook function will sanitize usernames and
 * passwords in the user table when the --sanitize option is used.  It is
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
function sql_drush_sql_sync_sanitize($site) {
  $site_settings = drush_sitealias_get_record($site);
  $user_table_updates = array();
  $message_list = array();

  // Sanitize passwords.
  $newpassword = drush_get_option(array('sanitize-password', 'destination-sanitize-password'), 'password');
  if ($newpassword != 'no') {
    $major_version = drush_drupal_major_version();
    $pw_op = "";

    // In Drupal 6, passwords are hashed via the MD5 algorithm.
    if ($major_version == 6) {
      $pw_op = "MD5('$newpassword')";
    }
    // In Drupal 7, passwords are hashed via a more complex algorithm,
    // available via the user_hash_password function.
    elseif ($major_version >= 7) {
      include_once DRUPAL_ROOT . '/includes/password.inc';
      include_once DRUPAL_ROOT . '/includes/bootstrap.inc';
      $hash = user_hash_password($newpassword);
      $pw_op = "'$hash'";
    }
    if (!empty($pw_op)) {
      $user_table_updates[] = "pass = $pw_op";
      $message_list[] =  "passwords";
    }
  }

  // Sanitize email addresses.
  $newemail = drush_get_option(array('sanitize-email', 'destination-sanitize-email'), 'user+%uid@localhost');
  if ($newemail != 'no') {
    if (strpos($newemail, '%') !== FALSE) {
      // We need a different sanitization query for Postgres and Mysql.
      $db_driver = $site_settings['databases']['default']['default']['driver'];
      if ($db_driver == 'pgsql') {
        $email_map = array('%uid' => "' || uid || '", '%mail' => "' || replace(mail, '@', '_') || '", '%name' => "' || replace(name, ' ', '_') || '");
        $newmail =  "'" . str_replace(array_keys($email_map), array_values($email_map), $newemail) . "'";
      }
      else {
        $email_map = array('%uid' => "', uid, '", '%mail' => "', replace(mail, '@', '_'), '", '%name' => "', replace(name, ' ', '_'), '");
        $newmail =  "concat('" . str_replace(array_keys($email_map), array_values($email_map), $newemail) . "')";
      }
    }
    $user_table_updates[] = "mail = $newmail";
    $message_list[] = 'email addresses';
  }

  if (!empty($user_table_updates)) {
    $sanitize_query = "update users set " . implode(', ', $user_table_updates) . " where uid > 0;";
    drush_sql_register_post_sync_op('user-email', dt('Reset !message in user table', array('!message' => implode(' and ', $message_list))), $sanitize_query);
  }
  
}


function drush_sql_sync($source = NULL, $destination = NULL) {
  $source_settings = drush_sitealias_get_record($source);
  $destination_settings = drush_sitealias_get_record($destination);

  // Check to see if this is an sql-sync multiple command (multiple sources and multiple destinations)
  $is_multiple = drush_do_multiple_command('sql-sync', $source_settings, $destination_settings);

  if ($is_multiple === FALSE) {
    // Evaluate the source and destination specifications into options.
    // The options from the 'source-*' and 'target-*' aliases are set
    // in a drush context that has a lower priority than the command-line
    // options; this allows command-line options to override the default
    // values specified in a site-alias.
    drush_sitealias_set_alias_context($source_settings, 'source-');
    drush_sitealias_set_alias_context($destination_settings, 'target-');

    // Get the options for the source and target databases
    $source_db_url = _drush_sql_get_spec_from_options('source-', FALSE);
    // The host may have special ssh requirements
    $source_remote_ssh_options = drush_get_option('source-ssh-options');
    // rsync later will also have to know this option
    $source_rsync_options = array('ssh-options' => $source_remote_ssh_options);

    $target_db_url = _drush_sql_get_spec_from_options('target-', FALSE);
    // The host may have special ssh requirements
    $target_remote_ssh_options = drush_get_option('target-ssh-options');
    // rsync later will also have to know this option
    $target_rsync_options = array('ssh-options' => $target_remote_ssh_options);

    if (empty($source_db_url)) {
      return drush_set_error('DRUSH_DATABASE_NOT_FOUND', dt('Error: no database record could be found for !source', array('!source' => $source)));
    }
    if (empty($target_db_url)) {
      return drush_set_error('DRUSH_DATABASE_NOT_FOUND', dt('Error: no database record could be found for !destination', array('!destination' => $destination)));
    }

    // Set up the result file and the remote file.
    // If the result file is not set, then create a temporary file.
    // If the remote file is not set, use the same name for the remote
    // and local files and hope for the best.
    $source_dump = drush_sql_dump_file($source_db_url, 'source-');
    $target_dump = drush_sql_dump_file($target_db_url, 'target-');
    $use_temp_files = drush_get_option('temp');
    // Only use one dump file if both the source and the target are on the local machine
    if (!isset($source_db_url['remote-host']) && !isset($target_db_url['remote-host'])) {
      if ((!$target_db_url['dump-is-temp']) && ($source_db_url['dump-is-temp'])) {
        $source_dump = $target_dump;
        $source_db_url['dump-is-temp'] = FALSE;
      }
      else {
        $target_dump = $source_dump;
        $target_db_url['dump-is-temp'] = $source_db_url['dump-is-temp'];
      }
      $local_file = $source_dump;
    }
    else {
      // If one of the systems is remote, then set the --remove-source-files
      // rsync option if the source dump file is temporary.  This will get
      // rsync to clean up after us automatically; useful if the source is remote.
      if ($source_db_url['dump-is-temp']) {
        $source_rsync_options['remove-source-files'] = TRUE;
      }
      // Set $local_file to whichever side of the operation is local, or make
      // a temporary file if both source and target are remote.
      if (!isset($source_db_url['remote-host'])) {
        $local_file = $source_dump;
      }
      elseif (!isset($target_db_url['remote-host'])) {
        $local_file = $target_dump;
      }
      else {
        $local_file = drush_tempnam($source_db_url['database'] . ($source_db_url['database'] == $target_db_url['database'] ? '' : '-to-' . $target_db_url['database']) . '.sql.');
      }
    }

    // If source is remote, then use ssh to dump the database and then rsync to local machine
    // If source is local, call drush_sql_dump to dump the database to local machine
    // In either case, the '--no-dump' option will cause the sql-dump step to be skipped, and
    // we will import from the existing local file (first using rsync to fetch it if it does not exist)
    //
    // No dump affects both local and remote sql-dumps; it prevents drush sql-sync
    // from calling sql-dump when the local cache file is newer than the cache threshhold
    // No sync affects the remote sql-dump; it will prevent drush sql-sync from
    // rsyncing the local sql-dump file with the remote sql-dump file.
    $no_sync = drush_get_option(array('no-sync', 'source-no-sync'));
    $no_dump = drush_get_option(array('no-dump', 'source-no-dump'));
    $no_cache = drush_get_option(array('no-cache', 'source-no-cache'));
    if (!isset($no_cache)) {
      $cache = drush_get_option(array('cache', 'source-cache'));
      if (!isset($cache)) {
        $cache = 24; // Default cache is 24 hours if nothing else is specified.
      }
    }
    // If the 'cache' option is set, then we will set the no-dump option iff the
    // target file exists and its modification date is less than "cache" hours.
    if (isset($cache)) {
      if (file_exists($local_file) && (filesize($local_file) > 0)) {
        if ((time() - filemtime($local_file)) < ($cache * 60 * 60)) {
          drush_log(dt('Modification time of local dump file !file is less than !cache hours old. Use the --no-cache option to force a refresh.', array('!file' => $local_file, '!cache' => $cache)), 'warning');
          $no_dump = TRUE;
          $no_sync = TRUE;
        }
        else {
          drush_log(dt('Local sql cache file exists but is greater than !cache hours old.', array('!cache' => $cache)));
        }
      }
      else {
        drush_log('Local sql cache file does not exist.');
      }
    }

    $table_selection = array();
    if (!isset($no_dump)) {
      $table_selection = drush_sql_get_table_selection();
    }

    // Prompt for confirmation. This is destructive.
    if (!drush_get_context('DRUSH_SIMULATE')) {
      // Check to see if we are using a temporary file in a situation
      // where the user did not specify "--temp".
      if (($source_db_url['dump-is-temp'] || $target_db_url['dump-is-temp']) && (!isset($use_temp_files)) && (isset($source_db_url['remote-host'])  || isset($target_db_url['remote-host']))) {
        drush_print(dt('WARNING:  Using temporary files to store and transfer sql-dump.  It is recommended that you specify --source-dump and --target-dump options on the command line, or set \'%dump\' or \'%dump-dir\' in the path-aliases section of your site alias records. This facilitates fast file transfer via rsync.'));
      }

      if (array_key_exists('tables', $table_selection) && (count($table_selection['tables']) > 0)) {
        drush_print();
        drush_print(dt('  Only the following tables will be transferred: !list', array('!list' => implode(',', $table_selection['tables']))));
      }
      elseif (!empty($table_selection)) {
        $skip_tables_list = implode(',', $table_selection['skip'] + $table_selection['structure']);
        if(!empty($skip_tables_list)) {
          drush_print();
          drush_print(dt('  The following tables will be skipped: !list', array('!list' => $skip_tables_list)));
        }
      }

      // If there are multiple destinations, then
      // prompt once here and suppress the warning message
      // and the normal confirmation below.
      if (array_key_exists('site-list', $destination_settings)) {
        drush_print();
        drush_print(dt('You are about to sync the database from !source, overwriting all of the following targets:', array('!source' => $source)));
        foreach ($destination_settings['site-list'] as $one_destination) {
          drush_print(dt('  !target', array('!target' => $one_destination)));
        }
      }
      else {
        drush_print();
        $txt_source = (isset($source_db_url['remote-host']) ? $source_db_url['remote-host'] . '/' : '') . $source_db_url['database'];
        $txt_destination = (isset($target_db_url['remote-host']) ? $target_db_url['remote-host'] . '/' : '') . $target_db_url['database'];
        drush_print(dt("You will destroy data from !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
      }

      // If any sanitization operations are to be done, then get the
      // sanitization messages and print them as part of the confirmation.
      // If --sanitize was specified but there were no sanitize messages,
      // then warn that sanitization operations will be accumulated and
      // processed after the sync completes.
      $messages = _drush_sql_get_post_sync_messages();
      if ($messages) {
        drush_print();
        drush_print($messages);
      }
      else if (drush_get_option('deferred-sanitization', FALSE) && !drush_get_option('confirm-sanitizations', FALSE)) {
        drush_print();
        drush_print("WARNING: --sanitize was specified, but deferred (e.g. the source site is remote).  The sanitization operations will be determined after the database is copied to the local system and will be run without further confirmation.  Run with --confirm-sanitizations to force confirmation after the sync.");
      }

      // TODO: actually make the backup if desired.
      drush_print();
      drush_print(dt("You might want to make a backup first, using the sql-dump command.\n"));
      if (!drush_confirm(dt('Do you really want to continue?'))) {
        return drush_user_abort();
      }
    }

    if (isset($source_db_url['remote-host'])) {
      $source_remote_user = drush_get_option('source-remote-user');
      $source_at = '';
      if (isset($source_remote_user)) {
        $source_at ='@';
        $source_remote_pass = drush_get_option('source-remote-pass') ? ':' . drush_get_option('source-remote-pass') : '';
      }

      if (!isset($no_dump)) {
        $source_intermediate = $source_dump;
        $mv_intermediate = '';
        // If we are doing a remote dump and the source is not a temporary file,
        // then first dump to a temporary file and move it to the specified file after
        // the dump is complete.  This will reduce contention during simultaneous dumps
        // from different users sharing the same dump file.
        if (!$source_db_url['dump-is-temp']) {
          $source_intermediate = $source_dump . '-' . date("U");
          $mv_intermediate = '; mv -f ' . $source_intermediate . ' ' . $source_dump;
        }
        drush_set_option('result-file', $source_intermediate);
        list($dump_exec, $dump_file) = drush_sql_build_dump_command($table_selection, $source_db_url);
        $dump_exec .= $mv_intermediate;
        if (isset($cache) && !$source_db_url['dump-is-temp']) {
          // Inject some bash commands to remotely test the modification date of the target file
          // if the cache option is set.
          $dump_exec = 'if [ ! -s ' . $source_dump . ' ] || [ $((`date "+%s"`-`stat --format="%Y" ' . $source_dump . '`)) -gt ' . ($cache * 60 *  60) . ' ] ; then ' . $dump_exec . '; fi';
        }
        $dump_exec = "ssh $source_remote_ssh_options $source_remote_user$source_at" . $source_db_url['remote-host'] . " " . escapeshellarg($dump_exec);
      }
    }
    else {
      if (!isset($no_dump)) {
        drush_set_option('result-file', $local_file);
        list($dump_exec, $dump_file) = drush_sql_build_dump_command($table_selection, $source_db_url);
      }
      $no_sync = TRUE;
    }

    // Call sql-dump, either on the local machine or remotely via ssh, as appropriate.
    if (!empty($dump_exec)) {
      drush_op_system($dump_exec);
      // TODO: IF FAILURE THEN ABORT
    }

    // If the sql-dump was remote, then rsync the file over to the local machine.
    if (!isset($no_sync)) {
      // If the source file is a temporary file, then we will have rsync
      // delete it for us (remove-source-files option set above).
      if (!drush_core_call_rsync($source_remote_user . $source_at . $source_db_url['remote-host'] . ':' . $source_dump, $local_file, $source_rsync_options)) {
        return FALSE;
      }
    }

    // We will handle lists of destination sites differently from
    // single source-to-destination syncs.
    if (array_key_exists('site-list', $destination_settings)) {
      // Insure that we will not dump the source sql database
      // repeatedly, but will instead re-use it each time through
      // the redispatch loop.
      drush_set_option('no-dump', TRUE);
      drush_set_option('no-sync', TRUE);
      drush_set_option('source-dump', $source_dump);
      // Call sql-sync for each destination to push the $source_dump
      // to each target in turn.
      foreach ($destination_settings['site-list'] as $one_destination) {
        drush_do_command_redispatch('sql-sync', array($source, $one_destination));
      }
    }
    else {
      // Prior to database import, we will generate a "create database" command
      // if the '--create-db' option was specified.  Note that typically the
      // web server user will not have permissions to create a database; to specify
      // a different user to use with the create db command, the '--db-su' option
      // may be used.
      // Under postgres, "alter role username with createdb;" will give create database
      // permissions to the specified user if said user was not created with this right.
      $pre_import_commands = '';
      $create_db = drush_get_option('create-db');
      if (isset($create_db)) {
        $create_db_su = drush_sql_su($target_db_url);
        $db_su_connect = _drush_sql_connect($create_db_su);
        $pre_import_sql = drush_sql_build_createdb_sql($target_db_url);
        $pre_import_commands = sprintf('echo "%s" | %s; ', $pre_import_sql, $db_su_connect);
      }

      // Generate the import command
      $import_command = _drush_sql_connect($target_db_url);
      switch (_drush_sql_get_scheme($target_db_url)) {
        case 'mysql':
          $import_command .= ' --silent';
          break;
        case 'pgsql':
          $import_command .= ' -q';
          break;
      }

      // If destination is remote, then use rsync to push the database, then use ssh to import the database
      // If destination is local, then just import the database locally
      if (isset($target_db_url['remote-host'])) {
        $target_remote_user = drush_get_option('target-remote-user');
        $target_at = '';
        if (isset($target_remote_user)) {
          $target_at ='@';
          $target_remote_pass = drush_get_option('target-remote-pass') ? ':' . drush_get_option('target-remote-pass') : '';
        }

        if (!drush_core_call_rsync($local_file, $target_remote_user . $target_at . $target_db_url['remote-host'] . ':' . $target_dump, $target_rsync_options)) {
          return FALSE;
        }

        $connect_exec = $pre_import_commands . $import_command . ' < ' . $target_dump;
        $import_exec = "ssh $target_remote_ssh_options $target_remote_user$target_at" . $target_db_url['remote-host'] . ' ' . escapeshellarg($connect_exec);
        // delete the remote target file if it is a temporary file
        if ($target_db_url['dump-is-temp']) {
          $import_exec .= '; rm -f ' . escapeshellarg($target_dump);
        }
      }
      else {
        $import_exec = $pre_import_commands . $import_command . ' < ' . $local_file;
      }

      drush_op_system($import_exec);
      
      // After the database is imported into the destination, we
      // will check and see if we did not collect sanitization
      // operations in drush_sql_sync_init (i.e. because the source
      // site was remote), and if the destination site is local,
      // then we will call the sanitization hooks now.
      // This presumes an important precondition, that the code
      // files were sync'ed before the database was sync'ed.
      if (drush_get_option('deferred-sanitization', FALSE) && (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_SITE) == FALSE)) {
        $bootstrapped = drush_bootstrap_max_to_sitealias($destination_settings);
        if ($bootstrapped) {
          drush_command_invoke_all('drush_sql_sync_sanitize', $destination);
        }
      }
    }
  }
}

/**
 * Apply all post-sync operations that were registered in any pre-sync hook.
 * Follow the pattern of this function to make your own post-sync hook.
 * If changing the database, be sure to also include a pre-sync hook to
 * notify the user of the change that will be made.  @see drush_sql_pre_sql_sync().
 */
function drush_sql_post_sql_sync($source = NULL, $destination = NULL) {
  $options = drush_get_context('post-sync-ops');
  if (!empty($options)) {
    // If 'deferred-sanitization' is set, then we collected the
    // sanitization operations -after- the database sync, which
    // means they were not confirmed up-front.  We will show the
    // operations here, but we will not offer an opportunity to
    // confirm unless --confirm-sanitizations is specified.
    if (drush_get_option('deferred-sanitization', FALSE) || drush_get_option('confirm-sanitizations', FALSE)) {
      if (!drush_get_context('DRUSH_SIMULATE')) {
        $messages = _drush_sql_get_post_sync_messages();
        if ($messages) {
          drush_print();
          drush_print($messages);
          if (drush_get_option('confirm-sanitizations', FALSE)) {
            if (!drush_confirm(dt('Do you really want to sanitize?'))) {
              // Do not abort or return FALSE; that would trigger a rollback.
              // Just skip the sanitizations and signal that all is ok.
              drush_log(dt('Sanitizations skipped.'), 'ok');
              return TRUE;
            }
          }
        }
      }
    }
    
    $destination_settings = drush_sitealias_get_record($destination);
    $sanitize_query = '';
    foreach($options as $id => $data) {
      $sanitize_query .= $data['query'] . " ";
    }
    if ($sanitize_query) {
      if (!drush_get_context('DRUSH_SIMULATE')) {
        $result = drush_invoke_sitealias_args($destination_settings, "sql-query", array($sanitize_query));
      }
      else {
        drush_print("Executing on $destination: $sanitize_query");
      }
    }
  }
}
