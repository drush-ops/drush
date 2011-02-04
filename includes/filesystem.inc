<?php
// $Id$

/**
 * @defgroup filesystemfunctions Filesystem convenience functions.
 * @{
 */

/**
 * Deletes the provided file or folder and everything inside it.
 *
 * @param $dir
 *   The directory to delete
 * @return
 *   FALSE on failure, TRUE if everything was deleted
 */
function drush_delete_dir($dir) {
  if (!file_exists($dir)) {
    return TRUE;
  }
  if (!is_dir($dir)) {
    return unlink($dir);
  }
  foreach (scandir($dir) as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }
    if (!drush_delete_dir($dir.'/'.$item)) {
      return FALSE;
    }
  }
  return rmdir($dir);
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
 *   If TRUE, the destination will be deleted if it exists.
 * @return
 *   TRUE on success, FALSE on failure.
 */
function drush_copy_dir($src, $dest, $overwrite = FALSE) {
  // Preflight based on $overwrite if $dest exists.
  if (file_exists($dest)) {
    if ($overwrite) {
      drush_op('drush_delete_dir', $dest);
    }
    else {
      return drush_set_error('DRUSH_DESTINATION_EXISTS', dt('Destination directory !dest already exists.', array('!dest' => $dest)));
    }
  }
  // $src readable?
  if (!drush_op('is_readable', $src)) {
    return drush_set_error('DRUSH_SOURCE_NOT_EXISTS', dt('Source directory !src is not readable or does not exist.', array('!src' => $src)));
  }
  // $dest writable?
  if (!drush_op('is_writable', dirname($dest))) {
    return drush_set_error('DRUSH_DESTINATION_NOT_WRITABLE', dt('Destination directory !dest is not writable.', array('!dest' => dirname($dest))));
  }
  // Try to do a recursive copy.
  if (@drush_op('_drush_recursive_copy', $src, $dest)) {
    return TRUE;
  }

  return drush_set_error('DRUSH_COPY_DIR_FAILURE', dt('Unable to copy !src to !dest.', array('src' => $src, 'dest' => $dest)));
}

/**
 * Internal function called by drush_copy_dir; do not use directly.
 */
function _drush_recursive_copy($src, $dest) {
  // all subdirectories and contents:
  if(is_dir($src)) {
    drush_mkdir($dest);
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
  elseif (drush_op('copy', $src, $dest) !== TRUE) {
    return FALSE;
  }

  return TRUE;
}

/**
 * Move $src to $dest.
 *
 * If the php 'rename' function doesn't work, then we'll do copy & delete.
 *
 * @param $src
 *   The directory to move.
 * @param $dest
 *   The destination to move the source to, including the new name of
 *   the directory.  To move directory "a" from "/b" to "/c", then
 *   $src = "/b/a" and $dest = "/c/a".  To move "a" to "/c" and rename
 *   it to "d", then $dest = "/c/d" (just like php rename function).
 * @param $overwrite
 *   If TRUE, the destination will be deleted if it exists.
 * @return
 *   TRUE on success, FALSE on failure.
 */
function drush_move_dir($src, $dest, $overwrite = FALSE) {
  // Preflight based on $overwrite if $dest exists.
  if (file_exists($dest)) {
    if ($overwrite) {
      drush_op('drush_delete_dir', $dest);
    }
    else {
      return drush_set_error('DRUSH_DESTINATION_EXISTS', dt('Destination directory !dest already exists.', array('!dest' => $dest)));
    }
  }
  // $src readable?
  if (!drush_op('is_readable', $src)) {
    return drush_set_error('DRUSH_SOURCE_NOT_EXISTS', dt('Source directory !src is not readable or does not exist.', array('!src' => $src)));
  }
  // $dest writable?
  if (!drush_op('is_writable', dirname($dest))) {
    return drush_set_error('DRUSH_DESTINATION_NOT_WRITABLE', dt('Destination directory !dest is not writable.', array('!dest' => dirname($dest))));
  }
  // Try rename. It will fail if $src and $dest are not in the same partition.
  if (@drush_op('rename', $src, $dest)) {
    return TRUE;
  }
  // Eventually it will create an empty file in $dest. See
  // http://www.php.net/manual/es/function.rename.php#90025
  elseif (is_file($dest)) {
    drush_op('unlink', $dest);
  }

  // If 'rename' fails, then we will use copy followed
  // by a delete of the source.
  if (drush_copy_dir($src, $dest)) {
    drush_op('drush_delete_dir', $src);
    return TRUE;
  }

  return drush_set_error('DRUSH_MOVE_DIR_FAILURE', dt('Unable to move !src to !dest.', array('src' => $src, 'dest' => $dest)));
}

/**
 * Cross-platform compatible helper function to recursively create a directory tree.
 * @see http://theserverpages.com/php/manual/en/function.mkdir.php#50383
 */
function drush_mkdir($path) {
  return is_dir($path) || (drush_mkdir(dirname($path)) && drush_shell_exec('mkdir %s', $path));
}

/**
 * Save a string to a temporary file. Does not depend on Drupal's API.
 * The temporary file will be automatically deleted when drush exits.
 *
 * @param string $data
 * @return string
 *   A path to the file.
 */
function drush_save_data_to_temp_file($data) {
  static $fp;

  $fp = tmpfile();
  fwrite($fp, $data);
  $meta_data = stream_get_meta_data($fp);
  $file = $meta_data['uri'];
  drush_register_file_for_deletion($file);

  return $file;
}

/**
 * Returns the path to a temporary directory.
 *
 * This is a custom version of file_directory_path().
 * We can't directly rely on sys_get_temp_dir() as this
 * path is not valid in some setups for Mac.
 */
function drush_find_tmp() {
  static $temporary_directory = NULL;

  if (is_null($temporary_directory)) {
    $directories = array();

    // Operating system specific dirs.
    if (substr(PHP_OS, 0, 3) == 'WIN') {
      $directories[] = 'c:\\windows\\temp';
      $directories[] = 'c:\\winnt\\temp';
    }
    else {
      $directories[] = '/tmp';
    }
    // This function exists in PHP 5 >= 5.2.1, but drush
    // requires PHP 5 >= 5.2.0, so we check for it.
    if (function_exists('sys_get_temp_dir')) {
      $directories[] = sys_get_temp_dir();
    }

    foreach ($directories as $directory) {
      if (is_dir($directory) && is_writable($directory)) {
        $temporary_directory = $directory;
        break;
      }
    }

    if (empty($temporary_directory)) {
      // If no directory has been found, create one in cwd.
      $temporary_directory = drush_cwd() . '/tmp';
      drush_mkdir($temporary_directory);
      if (!is_dir($directory)) {
        return drush_set_error('DRUSH_UNABLE_TO_CREATE_TMP_DIR', dt("Unable to create a temporary directory."));
      }
      drush_register_file_for_deletion($temporary_directory);
    }
  }

  return $temporary_directory;
}

/**
 * Creates a temporary file, and registers it so that
 * it will be deleted when drush exits.  Whenever possible,
 * drush_save_data_to_temp_file() should be used instead
 * of this function.
 */
function drush_tempnam($pattern, $tmp_dir = NULL) {
  if ($tmp_dir == NULL) {
    $tmp_dir = drush_find_tmp();
  }
  $tmp_file = tempnam($tmp_dir, $pattern);
  drush_register_file_for_deletion($tmp_file);

  return $tmp_file;
}

/**
 * Creates a temporary directory and return its path.
 */
function drush_tempdir() {
  $tmp_dir = rtrim(drush_find_tmp(), DIRECTORY_SEPARATOR);
  $tmp_dir .= '/' . 'drush_tmp_' . time();

  drush_mkdir($tmp_dir);
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
        drush_delete_dir($file);
      }
      else {
        unlink($file);
      }
    }
  }
}

/**
 * Decide where our backup directory should go
 *
 * @param string $subdir
 *   The name of the desired subdirectory(s) under drush-backups.
 *   Usually a database name.
 */
function drush_preflight_backup_dir($subdir = NULL) {
  $backup_dir = drush_get_context('DRUSH_BACKUP_DIR', drush_get_option('backup-location'));

  if (empty($backup_dir)) {

    // Try to use db name as subdir if none was provided.
    if (empty($subdir)) {
      $subdir = 'unknown';
      if ($creds = drush_get_context('DRUSH_DB_CREDENTIALS')) {
        $subdir = $creds['name'];
      }
    }

    // Save the date to be used in the backup directory's path name.
    $date = gmdate('YmdHis', $_SERVER['REQUEST_TIME']);

    $backup_dir = drush_get_option('backup-dir', drush_server_home() . '/' . 'drush-backups');
    $backup_dir = rtrim($backup_dir, DIRECTORY_SEPARATOR) . '/' . $subdir . '/' . $date;
    drush_set_context('DRUSH_BACKUP_DIR', $backup_dir);
  }
  return $backup_dir;
}

/**
 * Prepare a backup directory
 */
function drush_prepare_backup_dir($subdir = NULL) {
  $backup_dir = drush_preflight_backup_dir($subdir);
  $backup_parent = dirname($backup_dir);

  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  if ((!empty($drupal_root)) && (strpos($backup_parent, $drupal_root) === 0)) {
    return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('It\'s not allowed to store backups inside the Drupal root directory.'));
  }
  if (!file_exists($backup_parent)) {
    if (!drush_mkdir($backup_parent)) {
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Unable to create backup directory !dir.', array('!dir' => $backup_parent)));
    }
  }
  if (!is_writable($backup_parent)) {
    return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Backup directory !dir is not writable.', array('!dir' => $backup_parent)));
  }

  drush_mkdir($backup_dir);
  return $backup_dir;
}

/**
 * Test to see if a file exists and is not empty
 */
function drush_file_not_empty($file_to_test) {
  if (file_exists($file_to_test)) {
    $stat = stat($file_to_test);
    if ($stat['size'] > 0) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * @} End of "defgroup filesystemfunctions".
 */