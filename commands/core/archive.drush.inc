<?php

/**
 * @file
 *   An early implementation of Site Archive dump/restore. See
 *   http://groups.drupal.org/site-archive-format.
 */

function archive_drush_command() {
  $items['archive-dump'] = array(
    'description' => 'Backup your code, files, and database into a single file.',
    'arguments' => array(
      'sites' => 'Optional. Site specifications, delimited by commas. Typically, list subdirectory name(s) under /sites.',
    ),
    'options' => array(
      'description' => 'Describe the archive contents.',
      'tags' => 'Add tags to the archive manifest. Delimit multiple by commas.',
      'destination' => 'The full path and filename in which the archive should be stored. If omitted, it will be saved to the drush-backups directory and a filename will be generated.',
      'overwrite' => 'Do not fail if the destination file exists; overwrite it instead.',
      'generator' => 'The generator name to store in the MANIFEST file. The default is "Drush archive-dump".',
      'generatorversion' => 'The generator version number to store in the MANIFEST file. The default is ' . DRUSH_VERSION . '.',
      'pipe' => 'Only print the destination of the archive. Useful for scripts that don\'t pass --destination.',
    ),
    'examples' => array(
      'drush archive-dump default,example.com,foo.com' => 'Write an archive containing 3 sites in it.',
      'drush archive-dump @sites' => 'Save archive containing all sites in a multi-site.',
      'drush archive-dump default --destination=/backups/mysite.tar' => 'Save archive to custom location.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'aliases' => array('ard', 'archive-backup', 'arb'),
  );
  $items['archive-restore'] = array(
    'description' => 'Expand a site archive into a Drupal web site.',
    'arguments' => array(
      'file' => 'The site archive file that should be expanded.',
      'site name' => 'Optional. Which site within the archive you want to restore. Defaults to all.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'destination' => 'Specify where the Drupal site should be expanded, including the docroot. Defaults to the current working directory.',
      'db-prefix' => 'An optional table prefix to use during restore.',
      'db-url' => 'A Drupal 6 style database URL indicating the target for database restore. If not provided, the archived settings.php is used.',
      'db-su' => 'Account to use when creating the new database. Optional.',
      'db-su-pw' => 'Password for the "db-su" account. Optional.',
      'overwrite' => 'Allow drush to overwrite any files in the destination.',

    ),
    'examples' => array(
      'drush archive-restore ./example.tar.gz' => 'Restore the files and databases for all sites in the archive.',
      'drush archive-restore ./example.tar.gz example.com' => 'Restore the files and database for example.com site.',
      'drush archive-restore ./example.tar.gz --destination=/var/www/example.com/docroot' => 'Restore archive to a custom location.',
      'drush archive-restore ./example.tar.gz --db-url=mysql://root:pass@127.0.0.1/dbname' => 'Restore archive to a new database (and customize settings.php to point there.).',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('arr'),
  );
  return $items;
}

/**
 * Command callback. Generate site archive file.
 */
function drush_archive_dump($sites_subdirs = '@self') {
  $full = array();
  $aliases = drush_sitealias_resolve_sitespecs(explode(',', $sites_subdirs));
  foreach ($aliases as $key => $alias) {
    $full[$key] = $alias;
    if (($db_record = sitealias_get_databases_from_record($alias))) {
      $full[$key]['databases'] = $db_record;
    }
    else {
      $full[$key]['databases'] = array();
      drush_log(dt('DB definition not found for !alias', array('!alias' => $key)), 'notice');
    }
  }

  // The user can specify a destination filepath or not. That filepath might
  // end with .gz, .tgz, or something else. At the end of this command we will
  // gzip a file, and we want it to end up with the user-specified name (if
  // any), but gzip renames files and refuses to compress files ending with
  // .gz and .tgz, making our lives difficult. Solution:
  //
  // 1. Create a unique temporary base name to which gzip WILL append .gz.
  // 2. If no destination is provided, set $dest_dir to a backup directory and
  // $final_destination to be the unique name in that dir.
  // 3. If a destination is provided, set $dest_dir to that directory and
  // $final_destination to the exact name given.
  // 4. Set $destination, the actual working file we will build up, to the
  // unqiue name in $dest_dir.
  // 5. After gzip'ing $destination, rename $destination.gz to
  // $final_destination.
  //
  // Sheesh.

  // Create the unique temporary name.
  $date = gmdate('Ymd_his');
  if (empty($full)) {
    $prefix = 'none';
  }
  else {
    $first = current($full);
    $prefix = count($sites_subdirs) > 1 ? 'multiple_sites' : str_replace('/', '-', $first['databases']['default']['default']['database']);
  }
  $temp_dest_name = "$prefix.$date.tar";

  $final_destination = drush_get_option('destination');
  if (!$final_destination) {
    // No destination provided.
    $backup = drush_include_engine('version_control', 'backup');
    // TODO: this standard drush pattern leads to a slightly obtuse directory structure.
    $dest_dir = $backup->prepare_backup_dir('archive-dump');
    if (empty($dest_dir)) {
      $dest_dir = drush_tempdir();
    }
    $final_destination = "$dest_dir/$temp_dest_name.gz";
  }
  else {
    // Use the supplied --destination. If it is relative, resolve it
    // relative to the directory in which drush was invoked.
    $command_cwd = getcwd();
    drush_op('chdir', drush_get_context('DRUSH_OLDCWD', getcwd()));
    // This doesn't perform realpath on the basename, but that's okay. This is
    // not path-based security. We just use it for checking for perms later.
    drush_mkdir(dirname($final_destination));
    $dest_dir = realpath(dirname($final_destination));
    $final_destination = $dest_dir . '/' . basename($final_destination);
    drush_op('chdir', $command_cwd);
  }

  // $dest_dir is either the backup directory or specified directory. Set our
  // working file.
  $destination = "$dest_dir/$temp_dest_name";

  // Validate the FINAL destination. It should be a file that does not exist
  // (unless --overwrite) in a writable directory (and a writable file if
  // it exists). We check all this up front to avoid failing after a long
  // dump process.
  $overwrite = drush_get_option('overwrite');
  $dest_dir = dirname($final_destination);
  $dt_args = array('!file' => $final_destination, '!dir' => $dest_dir);
  if (is_dir($final_destination)) {
    drush_set_error('DRUSH_ARCHIVE_DEST_IS_DIR', dt('destination !file must be a file, not a directory.', $dt_args));
    return;
  }
  else if (file_exists($final_destination)) {
    if (!$overwrite) {
      drush_set_error('DRUSH_ARCHIVE_DEST_EXISTS', dt('destination !file exists; specify --overwrite to overwrite.', $dt_args));
      return;
    }
    else if (!is_writable($final_destination)) {
      drush_set_error('DRUSH_ARCHIVE_DEST_FILE_NOT_WRITEABLE', dt('destination !file is not writable.', $dt_args));
      return;
    }
  }
  else if (!is_writable($dest_dir)) {
    drush_set_error('DRUSH_ARCHIVE_DEST_DIR_NOT_WRITEABLE', dt('destination directory !dir is not writable.', $dt_args));
    return;
  }

  $docroot_path = realpath(drush_get_context('DRUSH_DRUPAL_ROOT'));
  $docroot = basename($docroot_path);
  $workdir = dirname($docroot_path);
  // Archive Drupal core, excluding sites dir.
  drush_shell_cd_and_exec($workdir, "tar --exclude '{$docroot}/sites' --dereference -cf %s %s", $destination, $docroot);
  // Add sites/all to the same archive.
  drush_shell_cd_and_exec($workdir, "tar --dereference -rf %s %s", $destination, "{$docroot}/sites/all");

  $tmp = drush_tempdir();
  // Dump the default database for each site and add to the archive.
  foreach ($full as $key => $alias) {
    if ($db = $alias['databases']['default']['default']) {
      // Use a subdirectory name matching the docroot name.
      drush_mkdir("{$tmp}/{$docroot}");
      // Ensure uniqueness by prefixing key if needed.
      $dbname = $db['database'];
      $result_file = count($full) == 1 ? "$tmp/$dbname.sql" : str_replace('@', '', "$tmp/$key-$dbname.sql");
      $all_dbs[$key] = array(
        'file' => $result_file,
        'driver' => $db['driver'],
      );
      $table_selection = drush_sql_get_table_selection();
      list($dump_exec, $dump_file) = drush_sql_build_dump_command($table_selection, $db, $result_file);
      drush_shell_exec($dump_exec);
      drush_shell_cd_and_exec($tmp, 'tar --dereference -rf %s %s', $destination, basename($result_file));
    }
  }

  // Build a manifest file AND add sites/$subdir to archive as we go.
  $platform = array(
    'datestamp' => time(),
    'formatversion' => '1.0',
    'generator' => drush_get_option('generator', 'Drush archive-dump'),
    'generatorversion' => drush_get_option('generatorversion', DRUSH_VERSION),
    'description' => drush_get_option('description', ''),
    'tags' => drush_get_option('tags', ''),
  );
  $contents = drush_export_ini(array('Global' => $platform));

  $i=0;
  foreach ($full as $key => $alias) {
    $status = drush_invoke_process($alias, 'core-status', array(), array());

    // Add the site specific directory to archive.
    if (!empty($status['object']['%paths']['%site'])) {
      drush_shell_cd_and_exec($workdir, "tar --dereference -rf %s %s", $destination,  "{$docroot}/sites/" . basename($status['object']['%paths']['%site']));
    }

    $site = array(
      'docroot' => DRUPAL_ROOT,
      'sitedir' => @$status['object']['%paths']['%site'],
      'files-public' => @$status['object']['%paths']['%files'],
      'files-private' => @$status['object']['%paths']['%private'],
    );
    // Add info for each DB connection (usually only 1);
    foreach ($all_dbs as $dbkey => $db) {
      // basename() is for sqlite's sake.
      $site["database-default-file"] = basename($db['file']);
      $site["database-default-driver"] = $db['driver'];
    }
    // The section title is the sites subdirectory name.
    $info[basename($site['sitedir'])] = $site;
    $contents .= "\n" . drush_export_ini($info);
    unset($info);
    $i++;
  }
  file_put_contents("{$tmp}/MANIFEST.ini", $contents);

  // Add manifest to archive.
  drush_shell_cd_and_exec($tmp, 'tar --dereference -rf %s %s', $destination, "MANIFEST.ini");

  // Compress the archive
  drush_shell_exec("gzip --no-name -f %s", $destination);

  // gzip appends .gz unless the name already ends in .gz, .tgz, or .taz.
  if ("{$destination}.gz" != $final_destination) {
    drush_move_dir("{$destination}.gz", $final_destination, $overwrite);
  }

  drush_log(dt('Archive saved to !dest', array('!dest' => $final_destination)), 'ok');
  drush_print_pipe($final_destination);
  return $final_destination;
}

/**
 * Command argument complete callback.
 *
 * @return
 *   List of site names/aliases for archival.
 */
function archive_archive_dump_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Command callback. Restore web site(s) from a site archive file.
 */
function drush_archive_restore($file, $site_id = NULL) {
  $tmp = drush_tempdir();

  if (!$files = drush_tarball_extract($file, $tmp)) {
    return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_EXTRACT', dt('Unable to extract site archive tarball to !tmp.', array('!tmp' => $tmp)));
  }

  $manifest = $tmp . '/MANIFEST.ini';
  if (file_exists($manifest)) {
    if (!$ini = parse_ini_file($manifest, TRUE)) {
      return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_PARSE_MANIFEST', dt('Unable to parse MANIFEST.ini in the archive.'));
    }
  }
  else {
    // No manifest. Try to find docroot and DB dump file.
    $db_file = drush_scan_directory($tmp, '/\.sql$/',  array('.', '..', 'CVS'), 0, 0);
    $directories = glob($tmp . '/*' , GLOB_ONLYDIR);
    $ini = array(
      'Global' => array(),
      'default' => array(
        'docroot' => reset($directories),
        'sitedir' => 'sites/default',
        'database-default-file' => key($db_file),
      ),
    );
  }

  // Grab the first site in the Manifest and move docroot to destination.
  $ini_tmp = $ini;
  unset($ini_tmp['Global']);
  $first = array_shift($ini_tmp);
  $docroot = basename($first['docroot']);
  $destination = drush_get_option('destination', realpath('.') . "/$docroot");
  if (!drush_move_dir("$tmp/$docroot", $destination, drush_get_option('overwrite'))) {
    return drush_set_error('DRUSH_ARCHIVE_UNABLE _RESTORE_FILES', dt('Unable to restore files to !dest', array('!dest' => $destination)));
  }

  // Loop over sites and restore databases and append to settings.php.
  foreach ($ini as $section => $site) {
    if ($section != 'Global' && (is_null($site_id) || $section == $site_id) && !empty($site['database-default-file'])) {

      // Restore database
      $sql_file = $tmp . '/' . $site['database-default-file'];
      if ($db_url = drush_get_option('db-url')) {
        if (empty($site_id) && count($ini) >= 3) {
          // TODO: Use drushrc to provide multiple db-urls for multi-restore?
          return drush_set_error('DRUSH_ARCHIVE_UNABLE_MULTI_RESTORE', dt('You must specify a site to restore when the archive contains multiple sites and a db-url is provided.'));
        }
        $db_spec = drush_convert_db_from_db_url($db_url);
      }
      else {
        $site_specification = $destination . '#' . $section;
        if ($return = drush_invoke_process($site_specification, 'sql-conf', array(), array('all' => TRUE), array('integrate' => FALSE, 'override-simulated' => TRUE))) {
          $databases = $return['object'];
          $db_spec = $databases['default']['default'];
        }
        else {
          return drush_set_error('DRUSH_ARCHIVE_UNABLE_DISCOVER_DB', dt('Unable to get database details from db-url option or settings.php', array('!key' => $key)));
        }
      }
      drush_sql_empty_db($db_spec);
      _drush_sql_query(NULL, $db_spec, $sql_file);

      // Append new DB info to settings.php.
      if ($db_url) {
        $settingsfile = $destination . '/' . $site['sitedir'] . '/settings.php';
        chmod($settingsfile, 0755); // Need to do something here or else we can't write.
        file_put_contents($settingsfile, "\n// Appended by drush archive-restore command.\n", FILE_APPEND);
        if (drush_drupal_major_version() >= 7) {
          file_put_contents($settingsfile, "\n" . '$databases = \'' . drush_sitealias_convert_db_from_db_url($db_url) . "';\n", FILE_APPEND);
        }
        else {
          file_put_contents($settingsfile, "\n" . '$db_url = \'' . $db_url . "';\n", FILE_APPEND);
        }
      }
    }
  }
  return $destination;
}


/**
 * Command argument complete callback.
 *
 * @return
 *   Strong glob of files to complete on.
 */
function archive_archive_restore_complete() {
  return array(
           'files' => array(
             'directories' => array(
               'pattern' => '*',
               'flags' => GLOB_ONLYDIR,
             ),
             'tar' => array(
               'pattern' => '*.tar.gz',
             ),
           ),
         );
}
