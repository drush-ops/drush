<?php

declare(strict_types=1);

namespace Drush\Boot;

/**
 * A value class with bootstrap levels.
 */
class DrupalBootLevels
{
    /**
     * This constant is only usable as the value of the 'bootstrap'
     * item of a command object, or as the parameter to
     * drush_bootstrap_to_phase.  It is not a real bootstrap state.
     */
    const MAX = -2;

    /**
     * No bootstrap.
     *
     * Commands that only preflight, but do not bootstrap, should use
     * a bootstrap level of NONE.
     */
    const NONE = 0;

    /**
     * Set up and test for a valid drupal root.
     *
     * Any code that interacts with an entire Drupal installation, and not a specific
     * site on the Drupal installation should use this bootstrap phase.
     */
    const ROOT = 1;

    /**
     * Set up a Drupal site directory and the correct environment variables to
     * allow Drupal to find the configuration file.
     *
     * If no site is specified with the -l / --uri options, Drush will assume the
     * site is 'default', which mimics Drupal's behaviour.
     *
     * If you want to avoid this behaviour, it is recommended that you use the
     * ROOT bootstrap phase instead.
     *
     * Any code that needs to modify or interact with a specific Drupal site's
     * settings.php file should bootstrap to this phase.
     */
    const SITE = 2;

    /**
     * Load the settings from the Drupal sites directory.
     *
     * This phase is analagous to the DRUPAL_BOOTSTRAP_CONFIGURATION bootstrap phase in Drupal
     * itself, and this is also the first step where Drupal specific code is included.
     *
     * This phase is commonly used for code that interacts with the Drupal install API,
     * as both install.php and update.php start at this phase.
     */
    const CONFIGURATION = 3;

    /**
     * Connect to the Drupal database using the database credentials loaded
     * during the previous bootstrap phase.
     *
     * This phase is analogous to the DRUPAL_BOOTSTRAP_DATABASE bootstrap phase in
     * Drupal.
     *
     * Any code that needs to interact with the Drupal database API needs to
     * be bootstrapped to at least this phase.
     */
    const DATABASE = 4;

    /**
     * Fully initialize Drupal.
     *
     * This is analogous to the DRUPAL_BOOTSTRAP_FULL bootstrap phase in
     * Drupal.
     *
     * Any code that interacts with the general Drupal API should be
     * bootstrapped to this phase.
     */
    const FULL = 5;

    public static function getPhaseName($index): string
    {
        $reflection = new \ReflectionClass(self::class);
        return strtolower(array_search($index, $reflection->getConstants()));
    }
}
