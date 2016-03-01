<?php

namespace Drush\Boot;

/**
 * Defines the interface for a Boot classes.  Any CMS that wishes
 * to work with Drush should extend BaseBoot.  If the CMS has a
 * Drupal-Compatibility layer, then it should extend DrupalBoot.
 *
 * @todo Doc these methods.
 */
interface Boot {
  /**
   * This function determines if the specified path points to
   * the root directory of a CMS that can be bootstrapped by
   * the specific subclass that implements it.
   *
   * These functions should be written such that one and only
   * one class will return TRUE for any given $path.
   *
   * @param $path to a directory to test
   *
   * @return TRUE if $path is a valid root directory
   */
  function valid_root($path);


  /**
   * Given a site root directory, determine the exact version of the software.
   *
   * @param string $root
   *   The full path to the site installation, with no trailing slash.
   * @return string|NULL
   *   The version string for the current version of the software, e.g. 8.1.3
   */
  function get_version($root);

  /**
   * Main entrypoint to bootstrap the selected CMS and
   * execute the selected command.
   *
   * The implementation provided in BaseBoot should be
   * sufficient; this method usually will not need to
   * be overridden.
   */
  function bootstrap_and_dispatch();

  /**
   * Returns an array that determines what bootstrap phases
   * are necessary to bootstrap this CMS.  This array
   * should map from a numeric phase to the name of a method
   * (string) in the Boot class that handles the bootstrap
   * phase.
   *
   * @see \Drush\Boot\DrupalBoot::bootstrap_phases()
   *
   * @return array of PHASE index => method name.
   */
  function bootstrap_phases();

  /**
   * List of bootstrap phases where Drush should stop and look for commandfiles.
   *
   * This allows us to bootstrap to a minimum neccesary to find commands.
   *
   * Once a command is found, Drush will ensure a bootstrap to the phase
   * declared by the command.
   *
   * @return array of PHASE indexes.
   */
  function bootstrap_init_phases();

  /**
   * Return an array of default values that should be added
   * to every command (e.g. values needed in enforce_requirements(),
   * etc.)
   */
  function command_defaults();

  /**
   * Called by Drush when a command is selected, but
   * before it runs.  This gives the Boot class an
   * opportunity to determine if any minimum
   * requirements (e.g. minimum Drupal version) declared
   * in the command have been met.
   *
   * @return TRUE if command is valid. $command['bootstrap_errors']
   * should be populated with an array of error messages if
   * the command is not valid.
   */
  function enforce_requirement(&$command);

  /**
   * Get a list of Drush services supported by this CMS.
   *
   * Services are a mapping from named string to control classes. Examples
   * include the diffent Sql, Batch, and User classes used in Drupal 7/8.
   *
   * @see Drush\Boot\Boot::add_service()
   *
   * @return array
   */
  function get_services();

  /**
   * Given a service name, return a single service class name.
   *
   * @param string $service_name
   *   The name of the service whose class is being retrieved.
   *
   * @return string|FALSE
   */
  function get_service($service_name);

  /**
   * Add a Drush service supported by this CMS.
   *
   * Services are typically registered upon the instantiation of a boot class in
   * its constructor.
   *
   * @see Drush\Boot\Boot::get_services()
   *
   * @param string $service_name
   *   The name of the service being registered.
   * @param string $class_name
   *   The fully qualified class name for the given service.
   */
  function add_service($service_name, $class_name);

  /**
   * Called by Drush if a command is not found, or if the
   * command was found, but did not meet requirements.
   *
   * The implementation in BaseBoot should be sufficient
   * for most cases, so this method typically will not need
   * to be overridden.
   */
  function report_command_error($command);

  /**
   * This method is called during the shutdown of drush.
   *
   * @return void
   */
  public function terminate();
}
