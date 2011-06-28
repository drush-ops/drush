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
      'targets' => 'Optional. Site specifications, delimited by commas. Typically, list subdirectory name(s) under /sites.',
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
    'aliases' => array('ard'),
  );
  $items['archive-restore'] = array(
    'description' => 'Expand a site archive into a Drupal web site.',
    'arguments' => array(
      'file' => 'The site archive file that should be expanded.',
      'site number' => 'Optional. Which site within the archive you want to restore. Defaults to all.',
    ),
    'options' => array(
      'destination' => 'Specify where the archive should be stored. Include filename but omit the .gz suffix.',
    ),
    'examples' => array(
      'drush archive-dump default,example.com,foo.com' => 'Write an archive containing 3 sites in it.',
      'drush archive-dump @sites' => 'Save archive containing all sites in a multi-site.',
      'drush archive-dump default --destination=/backups/mysite.tar' => 'Save archive to custom location.',
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
  $aliases = drush_sitealias_resolve_sitespecs(explode(',', $sites_subdirs));
  foreach ($aliases as $key => $alias) {
    if (($db_record = sitealias_get_databases_from_record($alias))) {
      $full[$key] = $alias += $db_record;
    }
    else {
      drush_log(dt('DB connections not found for !alias', array('!alias' => $alias)), 'error');
      return;
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
  $first = current($full);
  $prefix = count($sites_subdirs) > 1 ? 'multiple_sites' : $first['default']['default']['database'];
  $temp_dest_name = "$prefix.$date.tar";

  $final_destination = drush_get_option('destination');
  if (!$final_destination) {
    // No destination provided.
    drush_include_engine('version_control', 'backup');
    $backup = new drush_pm_version_control_backup();
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
  else if (!is_writable(dirname($final_destination))) {
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

  // Dump the database(s) for each site and add to the archive.
  foreach ($full as $key => $alias) {
    foreach ($alias['databases'] as $dbkey => $target) {
      $tmp = drush_tempdir();
      // Use a subdirectory name matching the docroot name.
      drush_mkdir("{$tmp}/{$docroot}");
      $result_file = "{$tmp}/{$target['default']['database']}.sql";
      drush_set_option('result-file', $result_file);
      $table_selection = drush_sql_get_table_selection();
      list($dump_exec, $dump_file) = drush_sql_build_dump_command($table_selection, $target['default']);
      drush_shell_exec($dump_exec);
      drush_shell_cd_and_exec($tmp, 'tar --dereference -rf %s %s', $destination, basename($result_file));
    }
  }

  // Build a manifest file AND add sites/$subdir to archive as we go.
  $platform = array(
    'datestamp' => time(),
    'formatversion' => '.1',
    'generator' => drush_get_option('generator', 'Drush archive-dump'),
    'generatorversion' => drush_get_option('generatorversion', DRUSH_VERSION),
    'description' => drush_get_option('description', ''),
    'tags' => explode(',', drush_get_option('tags', '')),
  );
  $contents = drush_export_info($platform);

  $i=0;
  foreach ($full as $key => $alias) {
    $vget = drush_invoke_process($alias, 'vget', array('site_name'), array());
    $name = $vget['output'];
    $pos1 = strpos($name, '"');
    $name = substr($name, $pos1);
    $status = drush_invoke_process($alias, 'core-status', array(), array());

    // Add the site specific directory to archive.
    if (!empty($status['object']['%paths']['%site'])) {
      drush_shell_cd_and_exec($workdir, "tar --dereference -rf %s %s", $destination,  "{$docroot}/sites/" . basename($status['object']['%paths']['%site']));
    }

    $site = array(
      'name' => str_replace(array('"', "\n"), '', $name),
      'docroot' => DRUPAL_ROOT,
      'sitedir' => @$status['object']['%paths']['%site'],
      'files' => array(
        'public' => @$status['object']['%paths']['%files'],
        'private' => @$status['object']['%paths']['%private'],
      ),
    );
    // Add info for each DB connection (usually only 1);
    foreach ($alias['databases'] as $dbkey => $target) {
      $site['database'][$dbkey]['file'] = './' . $target['default']['database'] . '.sql';
      $site['database'][$dbkey]['driver'] = $target['default']['driver'];
    }
    $info['sites'][$i] = $site;
    $contents .= "\n" . drush_export_info($info, TRUE);
    unset($info);
    $i++;
  }
  file_put_contents("{$tmp}/MANIFEST.info", $contents);

  // Add manifest to archive.
  drush_shell_cd_and_exec($tmp, 'tar --dereference -rf %s %s', $destination, "MANIFEST.info");

  // Compress the archive
  drush_shell_exec("gzip --no-name -f %s", $destination);

  // gzip appends .gz unless the name already ends in .gz, .tgz, or .taz.
  if ("{$destination}.gz" != $final_destination) {
    drush_move_dir("{$destination}.gz", $final_destination);
  }

  drush_log(dt('Archive saved to !dest', array('!dest' => $final_destination)), 'ok');
  drush_print_pipe($final_destination);
  return $final_destination;
}

/**
 * Command callback. Restore web site(s) from a site archive file.
 */
function drush_archive_restore($file, $site_id = NULL) {
  $destination = drush_get_option('destination', '.');
  //if (!$files = drush_tarball_extract($file, $destination)) {
  //  return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_EXTRACT', dt('Unable to extract site archive tarball.'));
  //}
  if (!$ini = parse_ini_file($destination . '/MANIFEST.info')) {
    return drush_set_error('DRUSH_ARCHIVE_UNABLE_TO_PARSE_MANIFEST', dt('Unable to parse MANIFEST.info in the archive.'));
  }

  print_r($ini);




}
