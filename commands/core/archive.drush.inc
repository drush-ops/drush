<?php

/**
 * @file
 *   An early implementation of Site Archive dump/restore. See
 *   http://groups.drupal.org/site-archive-format.
 */

use Drush\Log\LogLevel;

function archive_drush_command() {
  $items['archive-dump'] = array(
    'description' => 'Backup your code, files, and database into a single file.',
    'arguments' => array(
      'sites' => 'Optional. Site specifications, delimited by commas. Typically, list subdirectory name(s) under /sites.',
    ),
    // Most options on sql-dump should not be shown, so just offer a subset.
    'options' => drush_sql_get_table_selection_options() + array(
      'description' => 'Describe the archive contents.',
      'tags' => 'Add tags to the archive manifest. Delimit multiple by commas.',
      'destination' => 'The full path and filename in which the archive should be stored. If omitted, it will be saved to the drush-backups directory and a filename will be generated.',
      'overwrite' => 'Do not fail if the destination file exists; overwrite it instead. Default is --no-overwrite.',
      'generator' => 'The generator name to store in the MANIFEST file. The default is "Drush archive-dump".',
      'generatorversion' => 'The generator version number to store in the MANIFEST file. The default is ' . DRUSH_VERSION . '.',
      'pipe' => 'Only print the destination of the archive. Useful for scripts that don\'t pass --destination.',
      'preserve-symlinks' => 'Preserve symbolic links.',
      'no-core' => 'Exclude Drupal core, so the backup only contains the site specific stuff.',
      'tar-options' => 'Options passed thru to the tar command.',
    ),
    'examples' => array(
      'drush archive-dump default,example.com,foo.com' => 'Write an archive containing 3 sites in it.',
      'drush archive-dump @sites' => 'Save archive containing all sites in a multi-site.',
      'drush archive-dump default --destination=/backups/mysite.tar' => 'Save archive to custom location.',
      'drush archive-dump --tar-options="--exclude=.git --exclude=sites/default/files"' => 'Omits any .git directories found in the tree as well as sites/default/files.',
      'drush archive-dump --tar-options="--exclude=%files"' => 'Placeholder %files is replaced with the real path for the current site, and that path is excluded.',
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
      'db-url' => array(
        'description' => 'A Drupal 6 style database URL indicating the target for database restore. If not provided, the archived settings.php is used.',
        'example-value' => 'mysql://root:pass@host/db',
      ),
      'db-su' => 'Account to use when creating the new database. Optional.',
      'db-su-pw' => 'Password for the "db-su" account. Optional.',
      'overwrite' => 'Allow drush to overwrite any files in the destination. Default is --no-overwrite.',
      'tar-options' => 'Options passed thru to the tar command.',
    ),
    'examples' => array(
      'drush archive-restore ./example.tar.gz' => 'Restore the files and databases for all sites in the archive.',
      'drush archive-restore ./example.tar.gz example.com' => 'Restore the files and database for example.com site.',
      'drush archive-restore ./example.tar.gz --destination=/var/www/example.com/docroot' => 'Restore archive to a custom location.',
      'drush archive-restore ./example.tar.gz --db-url=mysql://root:pass@127.0.0.1/dbname' => 'Restore archive to a new database (and customize settings.php to point there.).',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'aliases' => array('arr'),
  );
  return $items;
}

/**
 * Command callback. Generate site archive file.
 */
function drush_archive_dump($sites_subdirs = '@self') {
  $include_platform = !drush_get_option('no-core', FALSE);
  $tar = drush_get_tar_executable();

  $sites = array();
  list($aliases, $not_found) = drush_sitealias_resolve_sitespecs(explode(',', $sites_subdirs));
  if (!empty($not_found)) {
    drush_log(dt("Some site subdirectories are not valid Drupal sites: @list", array("@list" => implode(', ', $not_found))), LogLevel::WARNING);
  }
  foreach ($aliases as $key => $alias) {
    $sites[$key] = $alias;

    if (($db_record = sitealias_get_databases_from_record($alias))) {
      $sites[$key]['databases'] = $db_record;
    }
    else {
      $sites[$key]['databases'] = array();
      drush_log(dt('DB definition not found for !alias', array('!alias' => $key)), LogLevel::NOTICE);
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
  $prefix = 'none';
  if (!empty($sites)) {
    $first = current($sites);
    if ( !empty($first['databases']['default']['default']['database']) ) {
      $prefix = count($sites) > 1 ? 'multiple_sites' : str_replace('/', '-', $first['databases']['default']['default']['database']);
    }
  }
  $date = gmdate('Ymd_His');
  $temp_dest_name = "$prefix.$date.tar";

  $final_destination = drush_get_option('destination');
  if (!$final_destination) {
    // No destination provided.
    $backup = drush_include_engine('version_control', 'backup');
    // TODO: this standard Drush pattern leads to a slightly obtuse directory structure.
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
    drush_set_error('DRUSH_ARCHIVE_DEST_IS_DIR', dt('Destination !file must be a file, not a directory.', $dt_args));
    return;
  }
  else if (file_exists($final_destination)) {
    if (!$overwrite) {
      drush_set_error('DRUSH_ARCHIVE_DEST_EXISTS', dt('Destination !file exists; specify --overwrite to overwrite.', $dt_args));
      return;
    }
    else if (!is_writable($final_destination)) {
      drush_set_error('DRUSH_ARCHIVE_DEST_FILE_NOT_WRITEABLE', dt('Destination !file is not writable.', $dt_args));
      return;
    }
  }
  else if (!is_writable($dest_dir)) {
    drush_set_error('DRUSH_ARCHIVE_DEST_DIR_NOT_WRITEABLE', dt('Destination directory !dir is not writable.', $dt_args));
    return;
  }

  // Get the extra options for tar, if any
  $tar_extra_options = drush_sitealias_evaluate_paths_in_options(drush_get_option('tar-options', ''));

  // Start adding codebase to the archive.
  $docroot_path = realpath(drush_get_context('DRUSH_DRUPAL_ROOT'));
  $docroot = basename($docroot_path);
  $workdir = dirname($docroot_path);

  if ($include_platform) {
    $dereference = (drush_get_option('preserve-symlinks', FALSE)) ? '' : '--dereference ';
    // Convert destination path to Unix style for tar on MinGW - see http://drupal.org/node/1844224
    if (drush_is_mingw()) {
      $destination_orig = $destination;
      $destination = str_replace('\\', '/', $destination);
      $destination = preg_replace('$^([a-zA-Z]):$', '/$1', $destination);
    }
    // Archive Drupal core, excluding sites dir.
    drush_shell_cd_and_exec($workdir, "$tar {$tar_extra_options} --exclude \"{$docroot}/sites\" {$dereference}-cf %s %s", $destination, $docroot);
    // Add sites/all to the same archive.
    drush_shell_cd_and_exec($workdir, "$tar {$tar_extra_options} {$dereference}-rf %s %s", $destination, "{$docroot}/sites/all");
    // Add special files in sites/ to the archive. Last 2 items are new in Drupal8.
    $files_to_add = array('sites/README.txt', 'sites/sites.php', 'sites/example.sites.php', 'sites/development.services.yml', 'sites/example.settings.local.php');
    foreach ($files_to_add as $file_to_add) {
      if (file_exists($file_to_add)) {
        drush_shell_cd_and_exec($workdir, "$tar {$dereference}-rf %s %s", $destination, $docroot . '/' . $file_to_add);
      }
    }
  }

  $tmp = drush_tempdir();
  $all_dbs = array();
  // Dump the default database for each site and add to the archive.
  foreach ($sites as $key => $alias) {
    if (isset($alias['databases']['default']['default'])) {
      $db_spec = $alias['databases']['default']['default'];
      // Use a subdirectory name matching the docroot name.
      drush_mkdir("{$tmp}/{$docroot}");

      // Ensure uniqueness by prefixing key if needed. Remove path delimiters.
      $dbname = str_replace(DIRECTORY_SEPARATOR, '-', $db_spec['database']);
      $result_file = count($sites) == 1 ? "$tmp/$dbname.sql" : str_replace('@', '', "$tmp/$key-$dbname.sql");

      $all_dbs[$key] = array(
        'file' => basename($result_file),
        'driver' => $db_spec['driver'],
      );
      $sql = drush_sql_get_class($db_spec);
      $sql->dump($result_file);
      drush_shell_cd_and_exec($tmp, "$tar {$tar_extra_options} --dereference -rf %s %s", $destination, basename($result_file));
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
    'archiveformat' => ($include_platform ? 'platform' : 'site'),
  );
  $contents = drush_export_ini(array('Global' => $platform));

  $i=0;
  foreach ($sites as $key => $alias) {
    $status = drush_invoke_process($alias, 'core-status', array(), array(), array('integrate' => FALSE));
    if (!$status || $status['error_log']) {
      drush_log(dt('Unable to determine sites directory for !alias', array('!alias' => $key)), LogLevel::WARNING);
    }

    // Add the site specific directory to archive.
    if (!empty($status['object']['%paths']['%site'])) {
      drush_shell_cd_and_exec($workdir, "$tar {$tar_extra_options} --dereference -rf %s %s", $destination,  "{$docroot}/sites/" . basename($status['object']['%paths']['%site']));
    }

    $site = array(
      'docroot' => DRUPAL_ROOT,
      'sitedir' => @$status['object']['%paths']['%site'],
      'files-public' => @$status['object']['%paths']['%files'],
      'files-private' => @$status['object']['%paths']['%private'],
    );
    $site["database-default-file"] = $all_dbs[$key]['file'];
    $site["database-default-driver"] = $all_dbs[$key]['driver'];
    // The section title is the sites subdirectory name.
    $info[basename($site['sitedir'])] = $site;
    $contents .= "\n" . drush_export_ini($info);
    unset($info);
    $i++;
  }
  file_put_contents("{$tmp}/MANIFEST.ini", $contents);

  // Add manifest to archive.
  drush_shell_cd_and_exec($tmp, "$tar --dereference -rf %s %s", $destination, 'MANIFEST.ini');

  // Ensure that default/default.settings.php is in the archive. This is needed
  // by site-install when restoring a site without any DB.
  // NOTE: Windows tar file replace operation is broken so we have to check if file already exists.
  // Otherwise it will corrupt the archive.
  $res = drush_shell_cd_and_exec($workdir, "$tar -tf %s %s", $destination, $docroot . '/sites/default/default.settings.php');
  $output = drush_shell_exec_output();
  if (!$res || !isset($output[0]) || empty($output[0])) {
    drush_shell_cd_and_exec($workdir, "$tar --dereference -vrf %s %s", $destination, $docroot . '/sites/default/default.settings.php');
  }

  // Switch back to original destination in case it was modified for tar on MinGW.
  if (!empty($destination_orig)) {
    $destination = $destination_orig;
  }

  // Compress the archive
  if (!drush_shell_exec("gzip --no-name -f %s", $destination)) {
    // Clean up the tar file
    drush_register_file_for_deletion($destination);
    return drush_set_error(DRUSH_APPLICATION_ERROR, dt('Failed to gzip !destination', ['!destination' => $destination]));
  }

  // gzip appends .gz unless the name already ends in .gz, .tgz, or .taz.
  if ("{$destination}.gz" != $final_destination) {
    drush_move_dir("{$destination}.gz", $final_destination, $overwrite);
  }

  drush_log(dt('Archive saved to !dest', array('!dest' => $final_destination)), LogLevel::OK);
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

  // Get the extra options for tar, if any
  $tar_extra_options = drush_sitealias_evaluate_paths_in_options(drush_get_option('tar-options', ''));

  if (!$files = drush_tarball_extract($file, $tmp, FALSE, $tar_extra_options)) {
    return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_EXTRACT', dt('Unable to extract site archive tarball to !tmp.', array('!tmp' => $tmp)));
  }

  $manifest = $tmp . '/MANIFEST.ini';
  if (file_exists($manifest)) {
    if (!$ini = parse_ini_file($manifest, TRUE)) {
      return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_PARSE_MANIFEST', dt('Unable to parse MANIFEST.ini in the archive.'));
    }
  }
  else {
    $ini = drush_archive_guess_manifest($tmp);
  }

  // Backward compatibility: 'archiveformat' did not exist
  // in older versions of archive-dump.
  if (!isset( $ini['Global']['archiveformat'])) {
    $ini['Global']['archiveformat'] = 'platform';
  }

  // Grab the first site in the Manifest and move docroot to destination.
  $ini_tmp = $ini;
  unset($ini_tmp['Global']);
  $first = array_shift($ini_tmp);
  $docroot = basename($first['docroot']);
  $destination = drush_get_option('destination', realpath('.') . "/$docroot");

  if ($ini['Global']['archiveformat'] == 'platform') {
    // Move the whole platform in-place at once.
    if (!drush_move_dir("$tmp/$docroot", $destination, drush_get_option('overwrite'))) {
      return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_RESTORE_FILES', dt('Unable to restore platform to !dest', array('!dest' => $destination)));
    }
  }
  else {
    // When no platform is included we do this on a per-site basis.
  }

  // Loop over sites and restore databases and append to settings.php.
  foreach ($ini as $section => $site) {
    if ($section != 'Global' && (!isset($site_id) || $section == $site_id) && !empty($site['database-default-file'])) {
      $site_destination = $destination . '/' . $site['sitedir'];
      // Restore site, in case not already done above.
      if ($ini['Global']['archiveformat'] == 'site') {
        if (!drush_move_dir("$tmp/$docroot/" . $site['sitedir'], $site_destination, drush_get_option('overwrite'))) {
          return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_RESTORE_FILES', dt('Unable to restore site to !dest', array('!dest' => $site_destination)));
        }
      }

      // Restore database.
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
      $sql = drush_sql_get_class($db_spec);
      $sql->drop_or_create();
      $sql->query(NULL, $sql_file);

      // Append new DB info to settings.php.
      if ($db_url) {
        $settingsfile = $destination . '/' . $site['sitedir'] . '/settings.php';
        //If settings.php doesn't exist in the archive, create it from default.settings.php.
        if (!file_exists($settingsfile)) {
          drush_op('copy', $destination . '/sites/default/default.settings.php', $settingsfile);
        }
        // Need to do something here or else we can't write.
        chmod($settingsfile, 0664);
        file_put_contents($settingsfile, "\n// Appended by drush archive-restore command.\n", FILE_APPEND);
        if (drush_drupal_major_version($destination) >= 7) {
          file_put_contents($settingsfile, "\n" . '$databases = ' . var_export(drush_sitealias_convert_db_from_db_url($db_url), TRUE) . ";\n", FILE_APPEND);
        }
        else {
          file_put_contents($settingsfile, "\n" . '$db_url = \'' . $db_url . "';\n", FILE_APPEND);
        }
        drush_log(dt('Drush appended the new database configuration at settings.php. Optionally remove the old configuration manually.'), LogLevel::OK);
      }
    }
  }
  drush_log(dt('Archive restored to !dest', array('!dest' => $destination)), LogLevel::OK);

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

/**
 * Try to find docroot and DB dump file in an extracted archive.
 *
 * @param string $path The location of the extracted archive.
 * @return array The manifest data.
 */
function drush_archive_guess_manifest($path) {
  $db_file = drush_scan_directory($path, '/\.sql$/',  array('.', '..', 'CVS'), 0, 0);

  if (file_exists($path . '/index.php')) {
    $docroot = './';
  }
  else {
    $directories = glob($path . '/*' , GLOB_ONLYDIR);
    $docroot = reset($directories);
  }

  $ini = array(
    'Global' => array(
        // Very crude detection of a platform...
        'archiveformat' => (drush_drupal_version($docroot) ? 'platform' : 'site'),
    ),
    'default' => array(
      'docroot' => $docroot,
      'sitedir' => 'sites/default',
      'database-default-file' => key($db_file),
    ),
  );

  return $ini;
}
