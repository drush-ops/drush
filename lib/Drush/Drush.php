<?php

/**
 * @file
 * Definition of Drush\Drush.
 */

namespace Drush;

/**
 * The Drush API.
 */
class Drush {
	static $commandfiles_cache;
	
	/**
	 * Run a drush command in a new process
	 */
	static function drush($site, $command_name, $commandline_args = array(), $commandline_options = array(), $backend_options = TRUE) {
		return drush_invoke_process($site, $command_name, $commandline_args, $commandline_options, $backend_options);
	}

	/**
	 * Run a drush subcommand in the same process, with the same options as the primary command.
	 */
	static function invoke($command, $arguments = array()) {
		return drush_invoke($command, $arguments);
	}

	/**
	 * Register a Drush extension commandfile, and load its autoload
	 * file, if necessary.
	 */
	static function autoload($commandfile) {
		$already_added = self::commandfiles_cache()->add($commandfile);

		if (!$already_added) {
			$dir = dirname($commandfile);
			$candidates = array("vendor/autoload.php", "../../../vendor/autoload.php");
			$drush_autoload_file = drush_get_context('DRUSH_VENDOR_PATH', '');

			foreach ($candidates as $candidate) {
			    $autoload = $dir . '/' . $candidate;
			    if (file_exists($autoload) && (realpath($autoload) != $drush_autoload_file)) {
			    	include $autoload;
			    }
			}
		}
	}

	// ====== Things used internally by Drush ======

	static function set_commandfiles_cache($commandfiles_cache) {
		self::$commandfiles_cache = $commandfiles_cache;
	}
	
	static function commandfiles_cache() {
		if (!isset(self::$commandfiles_cache)) {
			self::$commandfiles_cache = new Command\Commandfiles();
		}
		return self::$commandfiles_cache;
	}
}
