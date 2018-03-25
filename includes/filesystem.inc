<?php

/**
 * @file
 * Filesystem utilities.
 */
use Drush\Drush;
use Drush\Sql\SqlBase;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * @defgroup filesystemfunctions Filesystem convenience functions.
 * @{
 */

/**
 * Behavior for drush_copy_dir() when destinations exist.
 */
define('FILE_EXISTS_ABORT', 0);
define('FILE_EXISTS_OVERWRITE', 1);
define('FILE_EXISTS_MERGE', 2);

/**
 * Deletes the specified file or directory and everything inside it.
 *
 * Usually respects read-only files and folders. To do a forced delete use
 * drush_delete_tmp_dir() or set the parameter $forced.
 *
 * @param string $dir
 *   The file or directory to delete.
 * @param bool $force
 *   Whether or not to try everything possible to delete the directory, even if
 *   it's read-only. Defaults to FALSE.
 * @param bool $follow_symlinks
 *   Whether or not to delete symlinked files. Defaults to FALSE--simply
 *   unlinking symbolic links.
 *
 * @return bool
 *   FALSE on failure, TRUE if everything was deleted.
 *
 * @deprecated Use \Symfony\Component\Filesystem\Filesystem::remove.
 */
function drush_delete_dir($dir, $force = FALSE, $follow_symlinks = FALSE) {
  // Do not delete symlinked files, only unlink symbolic links
  if (is_link($dir) && !$follow_symlinks) {
    return unlink($dir);
  }
  // Allow to delete symlinks even if the target doesn't exist.
  if (!is_link($dir) && !file_exists($dir)) {
    return TRUE;
  }
  if (!is_dir($dir)) {
    if ($force) {
      // Force deletion of items with readonly flag.
      @chmod($dir, 0777);
    }
    return unlink($dir);
  }
  if (drush_delete_dir_contents($dir, $force) === FALSE) {
    return FALSE;
  }
  if ($force) {
    // Force deletion of items with readonly flag.
    @chmod($dir, 0777);
  }
  return rmdir($dir);
}

/**
 * Deletes the contents of a directory.
 *
 * @param string $dir
 *   The directory to delete.
 * @param bool $force
 *   Whether or not to try everything possible to delete the contents, even if
 *   they're read-only. Defaults to FALSE.
 *
 * @return bool
 *   FALSE on failure, TRUE if everything was deleted.
 */
function drush_delete_dir_contents($dir, $force = FALSE) {
  $scandir = @scandir($dir);
  if (!is_array($scandir)) {
    return FALSE;
  }

  foreach ($scandir as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }
    if ($force) {
      @chmod($dir, 0777);
    }
    if (!drush_delete_dir($dir . '/' . $item, $force)) {
      return FALSE;
    }
  }
  return TRUE;
}

/**
 * Copy $src to $dest.
 *
 * @param $src
 *   The directory to copy.
 * @param $dest
 *   The destination to copy the source to, including the new name of
 *   the directory.  To copy directory "a" from "/b" to "/c", then
 *   $src = "/b/a" and $dest = "/c/a".  To copy "a" to "/c" and rename
 *   it to "d", then $dest = "/c/d".
 * @param $overwrite
 *   Action to take if destination already exists.
 *     - FILE_EXISTS_OVERWRITE - completely removes existing directory.
 *     - FILE_EXISTS_ABORT - aborts the operation.
 *     - FILE_EXISTS_MERGE - Leaves existing files and directories in place.
 * @return
 *   TRUE on success, FALSE on failure.
 *
 * @deprecated Use \Symfony\Component\Filesystem\Filesystem::copy.
 */
function drush_copy_dir($src, $dest, $overwrite = FILE_EXISTS_ABORT) {
  // Preflight based on $overwrite if $dest exists.
  if (file_exists($dest)) {
    if ($overwrite === FILE_EXISTS_OVERWRITE) {
      drush_op('drush_delete_dir', $dest, TRUE);
    }
    elseif ($overwrite === FILE_EXISTS_ABORT) {
      return drush_set_error('DRUSH_DESTINATION_EXISTS', dt('Destination directory !dest already exists.', array('!dest' => $dest)));
    }
    elseif ($overwrite === FILE_EXISTS_MERGE) {
      // $overwrite flag may indicate we should merge instead.
      drush_log(dt('Merging existing !dest directory', array('!dest' => $dest)));
    }
  }
  // $src readable?
  if (!is_readable($src)) {
    return drush_set_error('DRUSH_SOURCE_NOT_EXISTS', dt('Source directory !src is not readable or does not exist.', array('!src' => $src)));
  }
  // $dest writable?
  if (!is_writable(dirname($dest))) {
    return drush_set_error('DRUSH_DESTINATION_NOT_WRITABLE', dt('Destination directory !dest is not writable.', array('!dest' => dirname($dest))));
  }
  // Try to do a recursive copy.
  if (@_drush_recursive_copy($src, $dest)) {
    return TRUE;
  }

  return drush_set_error('DRUSH_COPY_DIR_FAILURE', dt('Unable to copy !src to !dest.', array('!src' => $src, '!dest' => $dest)));
}

/**
 * Internal function called by drush_copy_dir; do not use directly.
 */
function _drush_recursive_copy($src, $dest) {
  // all subdirectories and contents:
  if(is_dir($src)) {
    if (!mkdir($dest)) {
      return FALSE;
    }
    $dir_handle = opendir($src);
    while($file = readdir($dir_handle)) {
      if ($file != "." && $file != "..") {
        if (_drush_recursive_copy("$src/$file", "$dest/$file") !== TRUE) {
          return FALSE;
        }
      }
    }
    closedir($dir_handle);
  }
  elseif (is_link($src)) {
    symlink(readlink($src), $dest);
  }
  elseif (!copy($src, $dest)) {
    return FALSE;
  }

  // Preserve file modification time.
  // https://github.com/drush-ops/drush/pull/1146
  touch($dest, filemtime($src));

  // Preserve execute permission.
  if (!is_link($src) && (!function_exists('drush_is_windows') || !drush_is_windows())) {
    // Get execute bits of $src.
    $execperms = fileperms($src) & 0111;
    // Apply execute permissions if any.
    if ($execperms > 0) {
      $perms = fileperms($dest) | $execperms;
      chmod($dest, $perms);
    }
  }

  return TRUE;
}

/**
 * Recursively create a directory tree.
 *
 * @param path
 *   Path to directory to create.
 *
 * @throws IOException On any directory creation failure
 * @deprecated See \Symfony\Component\Filesystem\Filesystem::mkdir.
 */
function drush_mkdir($path) {
  $fs = new Filesystem();
  $fs->mkdir($path);
  return true;
}

/*
 * Determine if program exists on user's PATH.
 *
 * @return bool|null
 */
function drush_program_exists($program) {
  if (drush_has_bash()) {
    $bucket = drush_bit_bucket();

    // Remove environment variables (eg. PGPASSFILE=) before testing program.
    $program = preg_replace('#^([A-Z0-9]+=.+? )+#', '', $program);

    // Dont't use drush_op_system() since we don't want output during tests.
    system("command -v $program > $bucket 2>&1", $result_code);
    return $result_code === 0 ? TRUE : FALSE;
  }
}

/**
 * Save a string to a temporary file. Does not depend on Drupal's API.
 * The temporary file will be automatically deleted when drush exits.
 *
 * @param string $data
 * @param string $suffix
 *   Append string to filename. use of this parameter if is discouraged. @see
 *   drush_tempnam().
 * @return string
 *   A path to the file.
 */
function drush_save_data_to_temp_file($data, $suffix = NULL) {
  static $fp;

  $file = drush_tempnam('drush_', NULL, $suffix);
  $fp = fopen($file, "w");
  fwrite($fp, $data);
  $meta_data = stream_get_meta_data($fp);
  $file = $meta_data['uri'];
  fclose($fp);

  return $file;
}

/**
 * Returns the path to a temporary directory.
 *
 * @deprecated Use $this->getConfig()->tmp() in a ConfigAware command.
 */
function drush_find_tmp() {
  return Drush::config()->tmp();
}

/**
 * Creates a temporary file, and registers it so that
 * it will be deleted when drush exits.  Whenever possible,
 * drush_save_data_to_temp_file() should be used instead
 * of this function.
 *
 * @param string $suffix
 *   Append this suffix to the filename. Use of this parameter is discouraged as
 *   it can break the guarantee of tempname(). See http://www.php.net/manual/en/function.tempnam.php#42052.
 *   Originally added to support Oracle driver.
 */
function drush_tempnam($pattern, $tmp_dir = NULL, $suffix = '') {
  if ($tmp_dir == NULL) {
    $tmp_dir = Drush::config()->tmp();
  }
  $tmp_file = tempnam($tmp_dir, $pattern);
  drush_register_file_for_deletion($tmp_file);
  $tmp_file_with_suffix = $tmp_file . $suffix;
  drush_register_file_for_deletion($tmp_file_with_suffix);
  return $tmp_file_with_suffix;
}

/**
 * Creates a temporary directory and return its path.
 */
function drush_tempdir() {
  $tmp_dir = Path::join(Drush::config()->tmp(), 'drush_tmp_' . uniqid(time() . '_'));

  $fs = new Filesystem();
  $fs->mkdir($tmp_dir);
  drush_register_file_for_deletion($tmp_dir);

  return $tmp_dir;
}

/**
 * Any file passed in to this function will be deleted
 * when drush exits.
 */
function drush_register_file_for_deletion($file = NULL) {
  static $registered_files = array();

  if (isset($file)) {
    if (empty($registered_files)) {
      register_shutdown_function('_drush_delete_registered_files');
    }
    $registered_files[] = $file;
  }

  return $registered_files;
}

/**
 * Delete all of the registered temporary files.
 */
function _drush_delete_registered_files() {
  $files_to_delete = drush_register_file_for_deletion();

  foreach ($files_to_delete as $file) {
    // We'll make sure that the file still exists, just
    // in case someone came along and deleted it, even
    // though they did not need to.
    if (file_exists($file)) {
      if (is_dir($file)) {
        drush_delete_dir($file, TRUE);
      }
      else {
        @chmod($file, 0777); // Make file writeable
        unlink($file);
      }
    }
  }
}

/**
 * Test to see if a file exists and is not empty
 */
function drush_file_not_empty($file_to_test) {
  if (file_exists($file_to_test)) {
    clearstatcache();
    $stat = stat($file_to_test);
    if ($stat['size'] > 0) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Return 'TRUE' if one directory is located anywhere inside
 * the other.
 */
function drush_is_nested_directory($base_dir, $test_is_nested) {
  $common = Path::getLongestCommonBasePath([$test_is_nested, $base_dir]);
  return $common == Path::canonicalize($base_dir);
}

/**
 * @} End of "defgroup filesystemfunctions".
 */
