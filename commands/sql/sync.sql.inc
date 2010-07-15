<?php
// $Id$

require_once DRUSH_BASE_PATH . '/commands/core/rsync.core.inc';

function drush_sql_sync($source = NULL, $destination = NULL) {
  $source_database = drush_get_option('source-database', 'default');
  $source_target = drush_get_option('source-target');
  $target_database = drush_get_option('target-database', 'default');
  $target_target = drush_get_option('target-target');

  //
  // If the destination was not explicitly set, but a particular
  // target database was specified on the command line, then we
  // will implicitly assume that the destination alias is the
  // same as the source alias.
  //
  if (!isset($destination) && (isset($target_database) || (isset($target_target)))) {
    $destination = $source;
  }

  //
  // If there is no destination specification, then exit.
  //
  if (!isset($destination)) {
    drush_print(dt("You must specify a destination target."));
    exit(1);
  }
  //
  // Default branch:  copy 'sync' with the specified source
  // and destination.
  //
  else {
    _drush_sql_sync($source, $destination, TRUE);
  }
}

function _drush_sql_sync($source, $destination, $show_warning = TRUE) {
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
    $source_dump = drush_get_option('source-dump');
    $target_dump = drush_get_option('target-dump');
    $use_temp_files = drush_get_option('temp');
    $source_is_tmp = FALSE;
    $target_is_tmp = FALSE;
    if (!isset($source_db_url['remote-host']) && !isset($target_db_url['remote-host'])) {
      if (isset($source_dump)) {
        $target_dump = $source_dump;
      }
      else {
        if (!isset($target_dump)) {
          $target_dump = drush_sql_dump_file($target_db_url);
          $target_is_tmp = TRUE;
        }
        $source_dump = $target_dump;
      }
    }
    if (!isset($target_dump)) {
      $target_dump = drush_sql_dump_file($target_db_url);
      $target_is_tmp = TRUE;
    }
    if (!isset($source_dump)) {
      $source_dump = drush_sql_dump_file($source_db_url);
      $source_is_tmp = TRUE;
      $source_rsync_options['remove-source-files'] = TRUE;
    }

    if (isset($source_db_url['remote-host']) && isset($target_db_url['remote-host'])) {
      $local_file = drush_tempnam($source_db_url['database'] . ($source_db_url['database'] == $target_db_url['database'] ? '' : '-to-' . $target_db_url['database']) . '.sql.');
    }
    elseif (!isset($source_db_url['remote-host'])) {
      $local_file = $source_dump;
    }
    elseif (!isset($target_db_url['remote-host'])) {
      $local_file = $target_dump;
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
        if ((filemtime($local_file) - time()) < ($cache * 60 * 60)) {
          drush_log(dt('Modification time of local dump file is less than !cache hours old. Use the --no-cache option to force a refresh.', array('!cache' => $cache)));
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
    if (!drush_get_context('DRUSH_SIMULATE') && $show_warning) {

      // If there are multiple destinations, then
      // prompt once here and suppress the warning message
      // and the normal confirmation below.
      if (array_key_exists('site-list', $destination_settings)) {
        drush_print(dt('You are about to sync the database from !source, overwriting all of the following targets:', array('!source' => $source)));
        foreach ($destination_settings['site-list'] as $one_destination) {
          drush_print(dt('  !target', array('!target' => $one_destination)));
        }
        drush_print();
      }
      else {
        // Check to see if we are using a temporary file in a situation
        // where the user did not specify "--temp".
        if (($source_is_tmp || $target_is_tmp) && (!isset($use_temp_files)) && (isset($source_db_url['remote-host'])  || isset($target_db_url['remote-host']))) {
          drush_print(dt('WARNING:  Using temporary files to store and transfer sql-dump.  It is recommended that you specify --source-dump and --target-dump options on the command line, or set \'%dump\' in the path-aliases section of your site alias records. This facilitates fast file transfer via rsync.'));
        }

        $txt_source = (isset($source_db_url['remote-host']) ? $source_db_url['remote-host'] . '/' : '') . $source_db_url['database'];
        $txt_destination = (isset($target_db_url['remote-host']) ? $target_db_url['remote-host'] . '/' : '') . $target_db_url['database'];
        drush_print(dt("You will destroy data from !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
        drush_print();
      }

      if (array_key_exists('tables', $table_selection) && (count($table_selection['tables']) > 0)) {
        drush_print(dt('  Only the following tables will be transferred: !list', array('!list' => implode(',', $table_selection['tables']))));
        drush_print();
      }
      elseif (!empty($table_selection)) {
        $skip_tables_list = implode(',', $table_selection['skip'] + $table_selection['structure']);
        if(!empty($skip_tables_list)) {
          drush_print(dt('  The following tables will be skipped: !list', array('!list' => $skip_tables_list)));
          drush_print();
        }
      }

      // TODO: actually make the backup if desired.
      drush_print(dt("You might want to make a backup first, using sql_dump command.\n"));
      if (!drush_confirm(dt('Do you really want to continue?'))) {
        drush_die('Aborting.');
      }
    }

    if (isset($source_db_url['remote-host'])) {
      $source_remote_user = drush_get_option('source-remote-user');
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
        if (!isset($source_is_tmp)) {
          $source_intermediate = $source_dump . '-' . date("U");
          $mv_intermediate = '; mv -f ' . $source_intermediate . ' ' . $source_dump;
        }
        drush_set_option('result-file', $source_intermediate);
        $dump_exec = drush_sql_build_dump_command($table_selection, $source_db_url) . $mv_intermediate;
        if (isset($cache) && !isset($source_is_tmp)) {
          // Inject some bash commands to remotely test the modification date of the target file
          // if the cache option is set.
          $dump_exec = 'if [ ! -s ' . $source_dump . '] || [ $((`date "+%s"`-`stat --format="%Y" ' . $source_dump . '`)) -gt ' . ($cache * 60 *  60) . ' ] ; then ' . $dump_exec . '; fi';
        }
        $dump_exec = "ssh $source_remote_ssh_options $source_remote_user$source_at" . $source_db_url['remote-host'] . " " . escapeshellarg($dump_exec);
      }
    }
    else {
      if (!isset($no_dump)) {
        drush_set_option('result-file', $local_file);
        $dump_exec = drush_sql_build_dump_command($table_selection, $source_db_url);
      }
      $no_sync = TRUE;
    }

    // Call sql-dump, either on the local machine or remotely via ssh, as appropriate.
    if (!empty($dump_exec)) {
      drush_op('system', $dump_exec);
      // TODO: IF FAILURE THEN ABORT
    }

    // If the sql-dump was remote, then rsync the file over to the local machine.
    if (!isset($no_sync)) {
      // If the source file is a temporary file, then we will have rsync
      // delete it for us (remove-source-files option set above).
      drush_core_call_rsync($source_remote_user . $source_at . $source_db_url['remote-host'] . ':' . $source_dump, $local_file, $source_rsync_options);
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
        $create_db_target = $target_db_url;
        $create_db_target['database'] = '';
        $db_superuser = drush_get_option(array('db-su', 'target-db-su'));
        if (isset($db_superuser)) {
          $create_db_target['username'] = $db_superuser;
        }
        $db_su_pw = drush_get_option(array('db-su-pw', 'target-db-su-pw'));
        if (isset($db_su_pw)) {
          $create_db_target['password'] = $db_su_pw;
        }
        $db_su_connect = _drush_sql_connect($create_db_target);
        switch (_drush_sql_get_scheme($target_db_url)) {
          case 'mysql':
            $pre_import_commands = 'echo "DROP DATABASE IF EXISTS ' . $target_db_url['database'] . '; CREATE DATABASE ' . $target_db_url['database'] . '; GRANT ALL PRIVILEGES ON ' . $target_db_url['database'] . '.* TO \'' . $target_db_url['username'] . '\'@\'' . $target_db_url['host'] . '\' IDENTIFIED BY \'' . $target_db_url['password'] . '\';" | ' . $db_su_connect . '; ';
            break;
          case 'pgsql':
            $pre_import_commands = 'echo "drop database if exists ' . $target_db_url['database'] . '; create database ' . $target_db_url['database'] . ';" | ' . $db_su_connect . '; ';
            break;
        }
      }

      // Generate the import command
      $import_command = _drush_sql_connect($target_db_url);
      switch (_drush_sql_get_scheme($target_db_url)) {
        case 'mysql':
          $import_command .= ' ' . (drush_get_context('DRUSH_DEBUG') ? ' ' : '--silent');
          break;
        case 'pgsql':
          $import_command .= ' ' . (drush_get_context('DRUSH_DEBUG') ? ' ' : '-q');
          break;
      }

      // If destination is remote, then use rsync to push the database, then use ssh to import the database
      // If destination is local, then just import the database locally
      if (isset($target_db_url['remote-host'])) {
        $target_remote_user = drush_get_option('target-remote-user');
        if (isset($target_remote_user)) {
          $target_at ='@';
          $target_remote_pass = drush_get_option('target-remote-pass') ? ':' . drush_get_option('target-remote-pass') : '';
        }

        drush_core_call_rsync($local_file, $target_remote_user . $target_at . $target_db_url['remote-host'] . ':' . $target_dump, $target_rsync_options);

        $connect_exec = $pre_import_commands . $import_command . ' < ' . $target_dump;
        $import_exec = "ssh $target_remote_ssh_options $target_remote_user$target_at" . $target_db_url['remote-host'] . ' ' . escapeshellarg($connect_exec);
        // delete the remote target file if it is a temporary file
        if ($target_is_tmp) {
          $import_exec .= '; rm -f ' . escapeshellarg($target_dump);
        }
      }
      else {
        $import_exec = $pre_import_commands . $import_command . ' < ' . $local_file;
      }

      drush_op('system', $import_exec);
    }
  }
}
