<?php
/**
 * @file
 * Download-specific functions for Drush Make.
 */

/**
 * Downloads the given package to the destination directory.
 *
 * @return mixed
 *   The destination path on success, FALSE on failure.
 */
function make_download_factory($name, $download, $download_location) {
  $function = 'make_download_' . $download['type'];
  if (function_exists($function)) {
    return $function($name, $download, $download_location);
  }
  else {
    return FALSE;
  }
}

/**
 * Download project using drush's pm-download command.
 */
function make_download_pm($name, $download, $download_location) {
  $full_project_version = $name . '-' . $download['full_version'];

  $options = array(
    'destination' => dirname($download_location),
    'yes' => TRUE,
    'package-handler' => 'wget',
    'source' => $download['status url'],
    // This is only relevant for profiles, but we always want the variant to
    // be 'profile-only' so we don't end up with extra copies of core.
    'variant' => 'profile-only',
    'cache' => TRUE,
  );
  if ($name == 'drupal') {
    $options['drupal-project-rename'] = basename($download_location);
  }
  if (drush_get_option('no-cache', FALSE)) {
    unset($options['cache']);
  }

  $backend_options = array();
  if (!drush_get_option(array('verbose', 'debug'), FALSE)) {
    $backend_options['integrate'] = TRUE;
    $backend_options['log'] = FALSE;
  }

  // Perform actual download with `drush pm-download`.
  $return = drush_invoke_process('@none', 'pm-download', array($full_project_version), $options, $backend_options);
  if (empty($return['error_log'])) {
    // @todo Report the URL we used for download. See
    // http://drupal.org/node/1452672.
    drush_log(dt('@project downloaded.', array('@project' => $full_project_version)), 'ok');
  }
}

/**
 * Downloads a file to the specified location.
 *
 * @return mixed
 *   The destination directory on success, FALSE on failure.
 */
function make_download_file($name, $download, $download_location, $cache_duration = DRUSH_CACHE_LIFETIME_DEFAULT) {
  if ($filename = _make_download_file($download['url'], $cache_duration)) {
    if (!drush_get_option('ignore-checksums') && !_make_verify_checksums($download, $filename)) {
      return FALSE;
    }
    drush_log(dt('@project downloaded from @url.', array('@project' => $name, '@url' => $download['url'])), 'ok');
    $download_filename = isset($download['filename']) ? $download['filename'] : '';
    $subtree = isset($download['subtree']) ? $download['subtree'] : NULL;
    return make_download_file_unpack($filename, $download_location, $download_filename, $subtree);
  }
  make_error('DOWNLOAD_ERROR', dt('Unable to download @project from @url.', array('@project' => $name, '@url' => $download['url'])));
  return FALSE;
}

/**
 * Wrapper to drush_download_file().
 *
 * @param string $download
 *   The url of the file to download.
 * @param int $cache_duration
 *   The time in seconds to cache the resultant download.
 *
 * @return string
 *   The location of the downloaded file, or FALSE on failure.
 */
function _make_download_file($download, $cache_duration = DRUSH_CACHE_LIFETIME_DEFAULT) {
  if (drush_get_option('no-cache', FALSE)) {
    $cache_duration = 0;
  }

  $tmp_path = make_tmp();
  // Ensure that we aren't including the querystring when generating a filename
  // to save our download to.
  $file = basename(reset(explode('?', $download, 2)));
  return drush_download_file($download, $tmp_path . '/' . $file, $cache_duration);
}

/**
 * Determines the MIME content type of the specified file. The power of this
 * function depends on whether the PHP installation has either
 * mime_content_type() or finfo installed -- if not, only tar, gz, and zip types
 * can be detected, and other file types will return 'unknown'.
 *
 * @return mixed
 *   The MIME content type of the file, 'unknown', or FALSE on error.
 */
function drush_mime_content_type($filename) {
  // Tar files have no magic value, so we can detect them only by filename.
  if (strrpos($filename, '.tar') === (strlen($filename) - 4)) {
    return 'application/x-tar';
  }

  $content_type = '';

  // mime_content_type() and the finfo class are not available everywhere.
  if (function_exists('mime_content_type')) {
    $content_type = mime_content_type($filename);
  }
  elseif (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME);
    // We only want the first part, before the ;
    $type = explode(';', $finfo->file($filename));
    $content_type = $type[0];
  }

  // If we found what we're looking for, we're done.
  if (!empty($content_type) && ($content_type == 'application/x-gzip' || $content_type == 'application/x-zip')) {
    return $content_type;
  }

  // If PHP's built-ins aren't present or PHP is configured in such a way that
  // all these files are considered octet-stream (e.g with mod_mime_magic and
  // an http conf that's serving all archives as octet-stream for other
  // reasons) we'll detect (a few select) mime types on our own by examing the
  // file's magic header bytes.
  if ($file = fopen($filename, 'rb')) {
    $first = fread($file, 2);
    fclose($file);

    if ($first === FALSE) {
      return FALSE;
    }

    // Interpret the two bytes as a little endian 16-bit unsigned int.
    $data = unpack('v', $first);
    switch ($data[1]) {
      case 0x8b1f:
        // First two bytes of gzip files are 0x1f, 0x8b.
        // See http://www.gzip.org/zlib/rfc-gzip.html#header-trailer.
        return 'application/x-gzip';

      case 0x4b50:
        // First two bytes of zip files are 0x50, 0x4b ('PK').
        // See http://en.wikipedia.org/wiki/Zip_(file_format)#File_headers.
        return 'application/zip';

      default:
        // Not a file type we can detect. If PHP's built-ins gave us
        // something, we can at least return that.
        return !empty($content_type) ? $content_type : 'unknown';
    }
  }
  return FALSE;
}

/**
 * Unpacks a file to the specified download location.
 *
 * @return mixed
 *   The download location on success, FALSE on failure.
 */
function make_download_file_unpack($filename, $download_location, $name, $subtree = NULL) {
  $success = FALSE;

  switch (drush_mime_content_type($filename)) {
    case 'application/x-gzip':
      // I'd like to just use tar -z, but apparently it breaks on windoze. Why
      // do they always have to ruin everything?
      $success = make_download_file_unpack_gzip($filename, $download_location, $subtree);
      break;

    case 'application/x-tar':
      $success = make_download_file_unpack_tar($filename, $download_location, $subtree);
      break;

    case 'application/x-zip':
    case 'application/zip':
      $success = make_download_file_unpack_zip($filename, $download_location, $subtree);
      break;

    default:
      // If this is an individual file, and no filename has been specified,
      // assume the original name.
      if (is_file($filename) && !$name) {
        $name = basename($filename);
      }

      // The destination directory has already been created by
      // findDownloadLocation().
      $destination = $download_location . ($name ? '/' . $name : '');
      $success = drush_move_dir($filename, $destination, TRUE);
  }
  return $success ? $download_location : FALSE;
}

/**
 * Unpacks a tar file to the specified location.
 *
 * @return boolean
 *   TRUE or FALSE depending on whether the operation was successful.
 */
function make_download_file_unpack_tar($filename, $download_location, $subtree = NULL) {
  $tmp_path = drush_tempdir();

  list($main_directory) = array_reverse(explode('/', $download_location));

  drush_shell_exec('%s -x -C %s -f %s', drush_get_tar_executable(), $tmp_path, $filename);

  return _make_download_file_move($tmp_path, $filename, $download_location, $subtree);
}

/**
 * Unpacks a gzip file to the specified location.
 *
 * @return boolean
 *   TRUE or FALSE depending on whether the operation was successful.
 */
function make_download_file_unpack_gzip($filename, $download_location, $subtree = NULL) {
  // Find out where contents will end up. Retrieve last column of output using
  // awk.
  drush_shell_exec("gzip --list %s", $filename);
  $info = drush_shell_exec_output();
  if ($info) {
    foreach ($info as $line) {
      $matches = array();
      preg_match('/^\s+[0-9]+\s+[0-9-]+\s+[0-9\.%]+\s+(.*)$/', $line, $matches);
      if (isset($matches[1])) {
        $file = $matches[1];
        break;
      }
    }
    if (isset($file)) {
      // Unzip it and then delete the tar file.
      drush_shell_exec('gzip -d %s', $filename);
      return make_download_file_unpack_tar($file, $download_location, $subtree);
    }
  }
}

/**
 * Unpack a zip file.
 */
function make_download_file_unpack_zip($filename, $download_location, $subtree = NULL) {
  $tmp_path = drush_tempdir();

  list($main_directory) = array_reverse(explode('/', $download_location));

  $result = drush_shell_exec("unzip %s -d %s", $filename, $tmp_path);

  if (!$result) {
    make_error('UNZIP_ERROR', dt('Unable to unzip @filename.', array('@filename' => $filename)));
    return FALSE;
  }

  return _make_download_file_move($tmp_path, $filename, $download_location, $subtree);
}

/**
 * Move a downloaded and unpacked file or directory into place.
 *
 * TODO merge with core drush methods.
 */
function _make_download_file_move($tmp_path, $filename, $download_location, $subtree = NULL) {
  $lines = drush_scan_directory($tmp_path, '/./', array('.', '..'), 0, FALSE, 'filename', 0, TRUE);
  $main_directory = basename($download_location);
  if (count($lines) == 1) {
    $directory = array_shift($lines);
    if ($directory->basename != $main_directory) {
      drush_move_dir($directory->filename, $tmp_path . DIRECTORY_SEPARATOR . $main_directory, TRUE);
    }
    drush_copy_dir($tmp_path . DIRECTORY_SEPARATOR . $main_directory . DIRECTORY_SEPARATOR . $subtree, $download_location, TRUE);
    drush_delete_dir($tmp_path, TRUE);
  }
  elseif (count($lines) > 1) {
    drush_delete_dir($download_location, TRUE);
    drush_move_dir($tmp_path . DIRECTORY_SEPARATOR . $subtree, $download_location, TRUE);
  }

  // Remove the tarball.
  if (file_exists($filename)) {
    drush_delete_dir($filename, TRUE);
  }

  if (file_exists($tmp_path)) {
    drush_delete_dir($tmp_path, TRUE);
  }
  return TRUE;
}


/**
 * For backwards compatibility.
 */
function make_download_get($name, $download, $download_location) {
  return make_download_file($name, $download, $download_location);
}

/**
 * Checks out a git repository to the specified download location.
 *
 * Allowed parameters in $download, in order of precedence:
 *   - 'tag'
 *   - 'revision'
 *   - 'branch'
 *
 * This will also attempt to write out release information to the
 * .info file if the 'no-gitinfofile' option is FALSE. If
 * $download['full_version'] is present, this will be used, otherwise,
 * version will be set in this order of precedence:
 *   - 'tag'
 *   - 'branch'
 *   - 'revision'
 *
 * @return mixed
 *   The download location on success, FALSE otherwise.
 */
function make_download_git($name, $download, $download_location) {
  $tmp_path = make_tmp();
  $wc = drush_get_option('working-copy');
  // If no download URL specified, assume anonymous clone from git.drupal.org.
  $download['url'] = isset($download['url']) ? $download['url'] : "http://git.drupal.org/project/$name.git";
  // If no working-copy download URL specified, assume it is the same.
  $download['wc_url'] = isset($download['wc_url']) ? $download['wc_url'] : $download['url'];

  // If not a working copy, and if --no-cache has not been explicitly
  // declared, create a new git reference cache of the remote repository,
  // or update the existing cache to fetch recent changes.
  // @see package_handler_download_project()
  $cache = !$wc && !drush_get_option('no-cache', FALSE);
  if ($cache && ($git_cache = drush_directory_cache('git'))) {
    $project_cache = $git_cache . '/' . $name . '-' . md5($download['url']);
    // Set up a new cache, if it doesn't exist.
    if (!file_exists($project_cache)) {
      $command = 'git clone --mirror';
      if (drush_get_context('DRUSH_VERBOSE')) {
        $command .= ' --verbose --progress';
      }
      $command .= ' %s %s';
      drush_shell_cd_and_exec($git_cache, $command, $download['url'], $project_cache);
    }
    else {
      // Update the --mirror clone.
      drush_shell_cd_and_exec($project_cache, 'git remote update');
    }
    $git_cache = $project_cache;
  }

  // Use working-copy download URL if --working-copy specified.
  $url = $wc ? $download['wc_url'] : $download['url'];

  $tmp_location = drush_tempdir() . '/' . basename($download_location);

  $command = 'git clone %s %s';
  if (drush_get_context('DRUSH_VERBOSE')) {
    $command .= ' --verbose --progress';
  }
  if ($cache) {
    $command .= ' --reference ' . drush_escapeshellarg($git_cache);
  }

  // Before we can checkout anything, we need to clone the repository.
  if (!drush_shell_exec($command, $url, $tmp_location)) {
    make_error('DOWNLOAD_ERROR', dt('Unable to clone @project from @url.', array('@project' => $name, '@url' => $url)));
    return FALSE;
  }

  drush_log(dt('@project cloned from @url.', array('@project' => $name, '@url' => $url)), 'ok');

  // Get the current directory (so we can move back later).
  $cwd = getcwd();
  // Change into the working copy of the cloned repo.
  chdir($tmp_location);

  // We want to use the most specific target possible, so first try a refspec.
  if (!empty($download['refspec'])) {
    if (drush_shell_exec("git fetch %s %s", $url, $download['refspec'])) {
      drush_log(dt("Fetched refspec !refspec.", array('!refspec' => $download['refspec'])), 'ok');

      if (drush_shell_exec("git checkout FETCH_HEAD")) {
        drush_log(dt("Checked out FETCH_HEAD."), 'info');
      }
    }
    else {
      make_error('DOWNLOAD_ERROR', dt("Unable to fetch the refspec @refspec from @project.", array('@refspec' => $download['refspec'], '@project' => $name)));
    }
  }

  // If there wasn't a refspec, try a tag.
  elseif (!empty($download['tag'])) {
    // @TODO: change checkout to refs path.
    if (drush_shell_exec("git checkout %s", 'refs/tags/' . $download['tag'])) {
      drush_log(dt("Checked out tag @tag.", array('@tag' => $download['tag'])), 'ok');
    }
    else {
      make_error('DOWNLOAD_ERROR', dt("Unable to check out tag @tag.", array('@tag' => $download['tag'])));
    }
  }

  // If there wasn't a tag, try a specific revision hash.
  elseif (!empty($download['revision'])) {
    if (drush_shell_exec("git checkout %s", $download['revision'])) {
      drush_log(dt("Checked out revision @revision.", array('@revision' => $download['revision'])), 'ok');
    }
    else {
      make_error('DOWNLOAD_ERROR', dt("Unable to checkout revision @revision", array('@revision' => $download['revision'])));
    }
  }

  // If not, see if we at least have a branch.
  elseif (!empty($download['branch'])) {
    if (drush_shell_exec("git checkout %s", $download['branch'])) {
      drush_log(dt("Checked out branch @branch.", array('@branch' => $download['branch'])), 'ok');
    }
    elseif (drush_shell_exec("git checkout -b %s %s", $download['branch'], 'origin/' . $download['branch'])) {
      drush_log(dt('Checked out branch origin/@branch.', array('@branch' => $download['branch'])), 'ok');
    }
    else {
      make_error('DOWNLOAD_ERROR', dt('Unable to check out branch @branch.', array('@branch' => $download['branch'])));
    }
  }

  if (!empty($download['submodule'])) {
    $command = 'git submodule update';
    foreach ($download['submodule'] as $option) {
      $command .= ' --%s';
    }
    if (call_user_func_array('drush_shell_exec', array_merge(array($command), $download['submodule']))) {
      drush_log(dt('Initialized registered submodules.'), 'ok');
    }
    else {
      make_error('DOWNLOAD_ERROR', dt('Unable to initialize submodules.'));
    }
  }

  // Move back to last current directory (first line).
  chdir($cwd);

  // Handle .info file re-writing (if so desired).
  if (!drush_get_option('no-gitinfofile', FALSE)) {
    // Figure out the proper version string to use based on the .make file.
    // Best case is the .make file author told us directly.
    if (!empty($download['full_version'])) {
      $full_version = $download['full_version'];
    }
    // Next best is if we have a tag, since those are identical to versions.
    elseif (!empty($download['tag'])) {
      $full_version = $download['tag'];
    }
    // If we have a branch, append '-dev'.
    elseif (!empty($download['branch'])) {
      $full_version = $download['branch'] . '-dev';
    }
    // Ugh. Not sure what else we can do in this case.
    elseif (!empty($download['revision'])) {
      $full_version = $download['revision'];
    }
    // Probably can never reach this case.
    else {
      $full_version = 'unknown';
    }

    // If the version string ends in '.x-dev' do the Git magic to figure out
    // the appropriate 'rebuild version' string, e.g. '7.x-1.2+7-dev'.
    $matches = array();
    if (preg_match('/^(.+).x-dev$/', $full_version, $matches)) {
      require_once dirname(__FILE__) . '/../pm/package_handler/git_drupalorg.inc';
      $full_version = drush_pm_git_drupalorg_compute_rebuild_version($tmp_location, $matches[1]);
    }
    require_once dirname(__FILE__) . '/../pm/pm.drush.inc';
    drush_pm_inject_info_file_metadata($tmp_location, $name, $full_version);
  }

  // Remove .git/ directory if working-copy flag was not specified.
  if (!$wc && file_exists($tmp_location . '/.git')) {
    drush_delete_dir($tmp_location . '/.git', TRUE);
  }

  // Move the directory into the final resting location.
  drush_copy_dir($tmp_location, $download_location, TRUE);

  return dirname($tmp_location);
}

/**
 * Checks out a Bazaar repository to the specified download location.
 *
 * @return mixed
 *   The download location on success, FALSE otherwise.
 */
function make_download_bzr($name, $download, $download_location) {
  $tmp_path = make_tmp();
  $tmp_location = drush_tempdir() . '/' . basename($download_location);
  if (!empty($download['url'])) {
    $args = array();
    $command = 'bzr';
    if (drush_get_option('working-copy')) {
      $command .= ' branch  --use-existing-dir';
    }
    else {
      $command .= ' export';
    }
    if (isset($download['revision'])) {
      $command .= ' -r %s';
      $args[] = $download['revision'];
    }
    $command .= ' %s %s';
    if (drush_get_option('working-copy')) {
      $args[] = $download['url'];
      $args[] = $tmp_location;
    }
    else {
      $args[] = $tmp_location;
      $args[] = $download['url'];
    }
    array_unshift($args, $command);
    if (call_user_func_array('drush_shell_exec', $args)) {
      drush_log(dt('@project downloaded from @url.', array('@project' => $name, '@url' => $download['url'])), 'ok');
      drush_copy_dir($tmp_location, $download_location, TRUE);
      return dirname($download_location);
    }
  }
  else {
    $download['url'] = dt("unspecified location");
  }
  make_error('DOWNLOAD_ERROR', dt('Unable to download @project from @url.', array('@project' => $name, '@url' => $download['url'])));
  drush_delete_dir(dirname($tmp_location), TRUE);
  return FALSE;
}

/**
 * Checks out an SVN repository to the specified download location.
 *
 * @return mixed
 *   The download location on success, FALSE otherwise.
 */
function make_download_svn($name, $download, $download_location) {
  if (!empty($download['url'])) {
    if (!empty($download['interactive'])) {
      $function = 'drush_shell_exec_interactive';
    }
    else {
      $options = ' --non-interactive';
      $function = 'drush_shell_exec';
    }
    if (!isset($download['force']) || $download['force']) {
      $options = ' --force';
    }
    if (drush_get_option('working-copy')) {
      $command = 'svn' . $options . ' checkout';
    }
    else {
      $command = 'svn' . $options . ' export';
    }

    $args = array();

    if (isset($download['revision'])) {
      $command .= ' -r%s';
      $args[] = $download['revision'];
    }

    $command .= ' %s %s';
    $args[] = $download['url'];
    $args[] = $download_location;

    if (!empty($download['username'])) {
      $command .= ' --username %s';
      $args[] = $download['username'];
      if (!empty($download['password'])) {
        $command .= ' --password %s';
        $args[] = $download['password'];
      }
    }
    array_unshift($args, $command);
    $result = call_user_func_array($function, $args);
    if ($result) {
      $args = array(
        '@project' => $name,
        '@command' => $command,
        '@url' => $download['url'],
      );
      drush_log(dt('@project @command from @url.', $args), 'ok');
      return $download_location;
    }
    else {
      $download['url'] = dt("unspecified location");
    }
  }
  else {
    make_error('DOWNLOAD_ERROR', dt('Unable to download @project from @url.', array('@project' => $name, '@url' => $download['url'])));
    return FALSE;
  }
}

/**
 * Test that any supplied hash values match the hash of the file content.
 *
 * Unsupported hash algorithms are reported as failure.
 */
function _make_verify_checksums($info, $filename) {
  $hash_algos = array('md5', 'sha1', 'sha256', 'sha512');
  // We only have something to do if a key is an
  // available function.
  if (array_intersect(array_keys($info), $hash_algos)) {
    $content = file_get_contents($filename);
    foreach ($hash_algos as $algo) {
      if (!empty($info[$algo])) {
        $hash = _make_hash($algo, $content);
        if ($hash !== $info[$algo]) {
          $args = array(
            '@algo' => $algo,
            '@file' => basename($filename),
            '@expected' => $info[$algo],
            '@hash' => $hash,
          );
          make_error('DOWNLOAD_ERROR', dt('Checksum @algo verification failed for @file. Expected @expected, received @hash.', $args));
          return FALSE;
        }
      }
    }
  }
  return TRUE;
}

/**
 * Calculate the hash of a string for a given algorithm.
 */
function _make_hash($algo, $string) {
  switch ($algo) {
    case 'md5':
      return md5($string);
    case 'sha1':
      return sha1($string);
    default:
      return function_exists('hash') ? hash($algo, $string) : '';
  }
}
