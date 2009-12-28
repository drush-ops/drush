<?php
// $Id$

require_once DRUSH_BASE_PATH . '/commands/core/rsync.inc';

function drush_sql_sql_sync($source = null, $destination = null) {
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
  $source_settings = drush_sitealias_get_record($source, TRUE);
  $destination_settings = drush_sitealias_get_record($destination, TRUE);

  // Evaluate the source and destination specifications into options.
  // The options from the 'source-*' and 'target-*' aliases are set
  // in a drush context that has a lower priority than the command-line
  // options; this allows command-line options to override the default
  // values specified in a site alias.
  drush_sitealias_set_alias_context($source_settings, 'source-');
  drush_sitealias_set_alias_context($destination_settings, 'target-');

  // Get the options for the source and target databases
  $source_db_url = _drush_sql_get_spec_from_options('source-');
  $source_remote_host = drush_get_option('source-remote-host');
  $source_remote_port = drush_get_option('source-remote-port');
  $source_rsync_options = array();

  $target_db_url = _drush_sql_get_spec_from_options('target-');
  $target_remote_host = drush_get_option('target-remote-host');
  $target_remote_port = drush_get_option('target-remote-port');
  $target_rsync_options = array();

  // Set up the result file and the remote file.
  // If the result file is not set, then create a temporary file.
  // If the remote file is not set, use the same name for the remote
  // and local files and hope for the best.
  $source_dump = drush_get_option('source-dump');
  $target_dump = drush_get_option('target-dump');
  $use_temp_files = drush_get_option('temp');
  $source_is_tmp = FALSE;
  $target_is_tmp = FALSE;
  $local_is_tmp = FALSE;
  if (!isset($source_remote_host) && !isset($target_remote_host)) {
    if (isset($source_dump)) {
      $target_dump = $source_dump;
    }
    else {
      if (!isset($target_dump)) {
        $target_dump = tempnam(sys_get_temp_dir(), $target_db_url['database'] . '.sql.');
        $target_is_tmp = TRUE;
      }
      $source_dump = $target_dump;
    }
  }
  if (!isset($target_dump)) {
    $target_dump = tempnam(sys_get_temp_dir(), $target_db_url['database'] . '.sql.');
    $target_is_tmp = TRUE;
  }
  if (!isset($source_dump)) {
    $source_dump = tempnam(sys_get_temp_dir(), $source_db_url['database'] . '.sql.');
    $source_is_tmp = TRUE;
    $source_rsync_options['remove-source-files'] = TRUE;
  }

  if (isset($source_remote_host) && isset($target_remote_host)) {
    $local_file = tempnam(sys_get_temp_dir(), $source_db_url['database'] . ($source_db_url['database'] == $target_db_url['database'] ? '' : '-to-' . $target_db_url['database']) . '.sql.');
    $local_is_tmp = TRUE;
  }
  elseif (!isset($source_remote_host)) {
    $local_file = $source_dump;
  }
  elseif (!isset($target_remote_host)) {
    $local_file = $target_dump;
  }

  // Check to see if we are using a temporary file in a situation
  // where the user did not specify "--temp".
  if (($source_is_tmp || $target_is_tmp) && (!isset($use_temp_files)) && (isset($source_remote_host)  || isset($target_remote_host))) {
    drush_print(dt('WARNING:  Using temporary files to store and transfer sql dump.  It is recommended that you specify --source-dump and --target-dump options on the command line, or set \'!dump\' in the path-aliases section of your site alias records. This facilitates fast file transfer via rsync.'));
  }

  // Prompt for confirmation. This is destructive.
  if (!drush_get_context('DRUSH_SIMULATE') && $show_warning) {
    $txt_source = (isset($source_remote_host) ? $source_remote_host . '/' : '') . $source_db_url['database'];
    $txt_destination = (isset($target_remote_host) ? $target_remote_host . '/' : '') . $target_db_url['database'];
    drush_print(dt("You will destroy data from !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
    drush_print();
    // TODO: actually make the backup if desired.
    drush_print(dt("You might want to make a backup first, using sql_dump command.\n"));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      if ($source_is_tmp) {
	unlink($source_dump);
      }
      if ($target_is_tmp) {
	unlink($target_dump);
      }
      if ($local_is_tmp) {
	unlink($local_file);
      }
      drush_die('Aborting.');
    }
  }

  // If source is remote, then use ssh to dump the database and then rsync to local machine
  // If source is local, call drush_sql_dump to dump the database to local machine
  // In either case, the '--no-dump' option will cause the sql dump step to be skipped, and
  // we will import from the existing local file (first using rsync to fetch it if it does not exist)
  //
  // No dump affects both local and remote sql dumps; it prevents drush sql sync
  // from calling sql dump when the local cache file is newer than the cache threshhold
  // No sync affects the remote sql dump; it will prevent drush sql sync from
  // rsyncing the local sql dump file with the remote sql dump file.
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
        drush_log(dt('Modification time of local dump file is less than !cache hours old.', array('!cache' => $cache)));
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
  if (isset($source_remote_host)) {
    if (isset($source_remote_port)) {
      $source_db_url['port'] = $source_remote_port;
    }
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
      $dump_exec = drush_sql_dump($source_db_url) . $mv_intermediate;
      if (isset($cache) && !isset($source_is_tmp)) {
        // Inject some bash commands to remotely test the modification date of the target file
        // if the cache option is set.
        $dump_exec = 'if [ ! -s ' . $source_dump . '] || [ $((`date "+%s"`-`stat --format="%Y" ' . $source_dump . '`)) -gt ' . ($cache * 60 *  60) . ' ] ; then ' . $dump_exec . '; fi';
      }
      $dump_exec = 'ssh ' . $source_remote_user . $source_at . $source_remote_host . ' ' . escapeshellarg($dump_exec);
    }
  }
  else {
    if (!isset($no_dump)) {
      drush_set_option('result-file', $local_file);
      $dump_exec = drush_sql_dump($source_db_url);
    }
    $no_sync = TRUE;
  }

  // Call sql dump, either on the local machine or remotely via ssh, as appropriate.
  if (!empty($dump_exec)) {
    drush_op('system', $dump_exec);
    // TODO: IF FAILURE THEN ABORT
  }

  // If the sql dump was remote, then rsync the file over to the local machine.
  if (!isset($no_sync)) {
    // If the source file is a temporary file, then we will have rsync
    // delete it for us (remove-source-files option set above).
    drush_core_call_rsync($source_remote_user . $source_at . $source_remote_host . ':' . $source_dump, $local_file, $source_rsync_options);
  }

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
    $db_su_connect = _drush_sql_connect($create_db_target);
    switch (_drush_sql_get_scheme($target_db_url)) {
      case 'mysql':
        $pre_import_commands = 'echo "CREATE DATABASE ' . $target_db_url['database'] . '; GRANT ALL PRIVILEGES ON ' . $target_db_url['database'] . '.* TO \'' . $target_db_url['username'] . '\'@\'localhost\' IDENTIFIED BY \'' . $target_db_url['password'] . '\';" | mysql --password=\'' . $target_db_url['password'] . '\'; ';
        break;
      case 'pgsql':
        $pre_import_commands = 'echo "create database ' . $target_db_url['database'] . ';" | ' . $db_su_connect . '; ';
        break;
    }
  }

  // Generate the import command
  $import_command = _drush_sql_connect($target_db_url);
  switch (_drush_sql_get_scheme($target_db_url)) {
    case 'mysql':
      $import_command .= ' ' . (drush_get_context('DRUSH_DEBUG') ? ' -v' : '--silent');
      break;
    case 'pgsql':
      $import_command .= ' ' . (drush_get_context('DRUSH_DEBUG') ? ' ' : '-q');
      break;
  }

  // If destination is remote, then use rsync to push the database, then use ssh to import the database
  // If destination is local, then just import the database locally
  if (isset($target_remote_host)) {
    if (isset($target_remote_port)) {
      $target_db_url['port'] = $target_remote_port;
    }
    $target_remote_user = drush_get_option('target-remote-user');
    if (isset($target_remote_user)) {
      $target_at ='@';
      $target_remote_pass = drush_get_option('target-remote-pass') ? ':' . drush_get_option('target-remote-pass') : '';
    }

    drush_core_call_rsync($local_file, $target_remote_user . $target_at . $target_remote_host . ':' . $target_dump);

    $connect_exec = $pre_import_commands . $import_command . ' < ' . $target_dump;
    $import_exec = 'ssh ' . $target_remote_user . $target_at . $target_remote_host . ' ' . escapeshellarg($connect_exec);
    // delete the remote target file if it is a temporary file
    if ($target_is_tmp) {
      $import_exec .= '; rm -f ' . escapeshellarg($target_dump);
    }
  }
  else {
    $import_exec = $pre_import_commands . $import_command . ' < ' . $local_file;
  }

  drush_op('system', $import_exec);
  // delete local temporary files
  if ($source_is_tmp) {
    unlink($source_dump);
  }
  if ($target_is_tmp) {
    unlink($target_dump);
  }
  if ($local_is_tmp) {
    unlink($local_file);
  }
}
