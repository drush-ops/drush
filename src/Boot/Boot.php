<?php

namespace Drush\Boot;

/**
 * Defines the interface for a Boot classes.  Any CMS that wishes
 * to work with Drush should extend BaseBoot.  If the CMS has a
 * Drupal-Compatibility layer, then it should extend DrupalBoot.
 */
interface Boot
{
    /**
     * Select the best URI for the provided cwd. Only called
     * if the user did not explicitly specify a URI.
     */
    public function findUri($root, $uri);

    /**
     * Inject the uri for the specific site to be bootstrapped
     *
     * @param string $uri Site to bootstrap
     */
    public function setUri(string $uri);

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
    public function validRoot($path);

    /**
     * Given a site root directory, determine the exact version of the software.
     *
     * @param string $root
     *   The full path to the site installation, with no trailing slash.
     * @return string|NULL
     *   The version string for the current version of the software, e.g. 8.1.3
     */
    public function getVersion(string $root);

    /**
     * Returns an array that determines what bootstrap phases
     * are necessary to bootstrap this CMS.  This array
     * should map from a numeric phase to the name of a method
     * (string) in the Boot class that handles the bootstrap
     * phase.
     *
     * @see \Drush\Boot\DrupalBoot::bootstrapPhases()
     *
     * @return array of PHASE index => method name.
     */
    public function bootstrapPhases();

    /**
     * Return an array mapping from bootstrap phase shorthand
     * strings (e.g. "full") to the corresponding bootstrap
     * phase index constant (e.g. DRUSH_BOOTSTRAP_DRUPAL_FULL).
     */
    public function bootstrapPhaseMap(): array;

    /**
     * Convert from a phase shorthand or constant to a phase index.
     */
    public function lookUpPhaseIndex($phase);

    /**
     * Called by Drush if a command is not found, or if the
     * command was found, but did not meet requirements.
     *
     * The implementation in BaseBoot should be sufficient
     * for most cases, so this method typically will not need
     * to be overridden.
     */
    public function reportCommandError($command);

    /**
     * This method is called during the shutdown of drush.
     *
     * @return void
     */
    public function terminate();
}
