<?php

/**
 * @file
 * Definition of Drush\Command\Commandfiles.
 */

namespace Drush\Command;

/**
 * Default commandfiles implementation.
 *
 * This class manages the list of commandfiles that are active
 * in Drush for the current command invocation.
 */
class Commandfiles implements CommandfilesInterface {
  protected $cache;
  protected $deferred;
  protected $needs_autoloader;
  protected $foreign_autoloaders;

  function __construct() {
    $this->cache = array();
    $this->deferred = array();
    $this->needs_autoloader = array();
    $this->foreign_autoloaders = array();
  }

  /**
   * @return the list of all cached commandfiles that were loaded.
   */
  function get() {
  	return $this->cache;
  }

  /**
   * @return the list of all commandfiles that were added, but have
   * not been loaded yet (waiting for a higher bootstrap level).
   */
  function deferred() {
  	return $this->deferred;
  }

  /**
   * Sort all of the cached commandfiles.  This is called once
   * Drush has finished calling 'add' for this bootstrap phase.
   */
  function sort() {
  	ksort($this->cache);
  }

  /**
   * Add a commandfile to the cache.  The commandfile might not be
   * immediately added; it may be saved in the 'deferred' list, and
   * then added on a later bootstrap phase, once it can be determined
   * to be acceptable.
   */
  function add($commandfile) {
	  $load_command = FALSE;

	  $module = basename($commandfile);
	  $module = preg_replace('/\.*drush[0-9]*\.inc/', '', $module);
	  $module_versionless = preg_replace('/\.d([0-9]+)$/', '', $module);
	  if (!isset($this->cache[$module_versionless])) {
	    $drupal_version = '';
	    if (preg_match('/\.d([0-9]+)$/', $module, $matches)) {
	      $drupal_version = $matches[1];
	    }
	    if (empty($drupal_version)) {
	      $load_command = TRUE;
	    }
	    else {
	      if (function_exists('drush_drupal_major_version') && ($drupal_version == drush_drupal_major_version())) {
	      	$load_command = TRUE;
	      }
	      else {
          // Signal that we should try again on
          // the next bootstrap phase.
          $this->deferred[$module] = $commandfile;
	      }
	    }
	    if ($load_command) {
        $this->load($module_versionless, $commandfile);
        unset($this->deferred[$module]);
	    }
	  }
	  return $load_command;
  }

  /**
   * Load a commandfile once it has been determined to be acceptable.
   */
  protected function load($key, $path) {
    $this->cache[$key] = $path;
    require_once $path;
    $this->check_needs_autoloader($key, $path);
  }

  /**
   * When a commandfile is loaded, check to see if it has need of
   * an autoload file.  Drush will keep track of this, and load
   * the autoload file if it is safe to do so.
   */
  protected function check_needs_autoloader($key, $path) {
    $dir = dirname($path);
    foreach (array('composer.json', '../composer.json') as $composer_file) {
      $composer_file_path = realpath($dir . '/' . $composer_file);
      if (file_exists($composer_file_path)) {
        $this->needs_autoloader[$key] = $composer_file_path;
        return;
      }
    }
  }

  /**
   * This function is called if Drush determines that it is safe to load
   * the autoload files.
   */
  function load_autoload_files() {
    $autoload_files = $this->find_autoload_files();
    foreach ($autoload_files as $key => $extension_autoload_file) {
      if (!array_key_exists($extension_autoload_file, $this->foreign_autoloaders)) {
        include $extension_autoload_file;
        $this->foreign_autoloaders[$extension_autoload_file][] = $key;
        drush_log(dt("Loading autoload file for !name.", array('!name' => $key)), 'notice');
      }
    }
  }

  /**
   * Find all of the autoload files associated with any Drush extension
   * that has a composer.json file.
   */
  function find_autoload_files() {
    $autoload_files = array();

    foreach ($this->needs_autoloader as $key => $composer_file_path) {
      $autoload_files += $this->find_autoload_file_for_extension($key);
    }

    return $autoload_files;
  }

  function find_autoload_file_for_extension($key) {
    $autoload_file = array();

    if (array_key_exists($key, $this->needs_autoloader)) {
      $composer_file_path = $this->needs_autoloader[$key];

      $dir = dirname($composer_file_path);
      $vendor_dir = $dir . '/vendor';
      // Load the composer file path, so we can determine if the vendor
      // directory has been moved.  If the vendor file is in its default
      // location, or in the location that is indicated in the composer.json
      // file, then this extension was installed independently.  Otherwise,
      // this extension was installed by being 'require'd in some other
      // project's composer.json file (e.g. as part of a Drupal site) or
      // via 'composer global require'.  In these instances, we can presume
      // that this extension's requirements have already been loaded as part
      // of the overall project's autoload.php file.
      $composer_contents = json_decode(file_get_contents($composer_file_path));
      if (isset($composer_contents->name)) {
        $name = $composer_contents->name;
        if (isset($composer_contents->config["vendor-dir"])) {
          $vendor_dir = $composer_contents->config["vendor-dir"];
        }
        $vendor_dir = realpath($vendor_dir);
        $drush_vendor_dir = drush_get_context('DRUSH_VENDOR_PATH', '');

        // If the autoload file exists, and is not the same autoload file
        // that Drush already loaded, then we will return it as part of
        // our result set.
        if (is_dir($vendor_dir) && ($vendor_dir != $drush_vendor_dir)) {
          $extension_autoload_file = realpath($vendor_dir . '/autoload.php');
          if (file_exists($extension_autoload_file)) {
            $autoload_file[$name] = $extension_autoload_file;
          }
        }
      }
    }

    return $autoload_file;
  }

  /**
   * Prevent dependency hell by forgetting about any commandfile
   * that has a foreign autoload file (an autoload.php that is
   * different than the one containing all of the autoload data
   * for Drush and the Drupal site).
   *
   * This function is only called if we are attempting to bootstrap
   * a site that uses Composer.
   */
  function prevent_dependency_hell() {
    foreach ($this->needs_autoloader as $key => $composer_file_path) {
      $has_autoload_file = $this->find_autoload_file_for_extension($key);
      if (!empty($has_autoload_file)) {
        drush_log(dt("Forgetting about Drush extension !extension to prevent potential autoloading problems.", array('!extension' => $key)), 'debug');
        unset($this->cache[$key]);
      }
    }
    // Now that we have forgotten about the extensions that need autoloaders,
    // clear our list of extensions that need autoloading, so that we do not
    // inadvertantly load any autoloaders later.
    $this->needs_autoloader = array();
  }
}
