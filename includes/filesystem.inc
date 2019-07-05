<?php

/**
 * @file
 * Legacy filesystem utilities.
 */
use Drush\Drush;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

/**
 * @defgroup filesystemfunctions Filesystem convenience functions.
 * @{
 */

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
  $tmp_file = Path::canonicalize(tempnam($tmp_dir, $pattern));
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
  $fs = new Filesystem();
  $files_to_delete = drush_register_file_for_deletion();

  foreach ($files_to_delete as $file) {
    // We'll make sure that the file still exists, just
    // in case someone came along and deleted it, even
    // though they did not need to.
    if (file_exists($file)) {
      if (is_dir($file)) {
        $fs->remove($file);
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
