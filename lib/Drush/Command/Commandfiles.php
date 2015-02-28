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

  function __construct() {
    $this->cache = array();
    $this->deferred = array();
  }

  function get() {
  	return $this->cache;
  }

  function deferred() {
  	return $this->deferred;
  }

  function sort() {
  	ksort($this->cache);
  }

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
	      $this->cache[$module_versionless] = $commandfile;
	      require_once $commandfile;
	      unset($this->deferred[$module]);
	    }
	  }
	  return $load_command;
  }
}
