<?php

declare(strict_types=1);

namespace Drush\Boot;

use Drush\Drush;
use Symfony\Component\Filesystem\Path;

abstract class DrupalBoot extends BaseBoot
{
    /**
     * Select the best URI for the provided cwd. Only called
     * if the user did not explicitly specify a URI.
     */
    public function findUri($root, $cwd): string
    {
        if (Path::isBasePath($root, $cwd)) {
            $siteDir = $this->scanUpForUri($root, $cwd);
            if ($siteDir) {
                return basename($siteDir);
            }
        }
        return 'default';
    }

    protected function scanUpForUri($root, $scan)
    {
        $root = Path::canonicalize($root);
        while (!empty($scan)) {
            if (file_exists("$scan/settings.php")) {
                return $scan;
            }
            // Use Path::getDirectory instead of dirname to
            // avoid certain bugs. Returns a canonicalized path.
            $next = Path::getDirectory($scan);
            if ($next == $scan) {
                return false;
            }
            $scan = $next;
            if ($scan === $root) {
                return false;
            }
        }
        return false;
    }

    public function validRoot(?string $path): bool
    {
    }

    public function getVersion($drupal_root)
    {
    }

    public function confPath(bool $require_settings = true, bool $reset = false): ?string
    {
    }

    /**
     * Bootstrap phases used with Drupal:
     *
     *     DRUSH_BOOTSTRAP_DRUSH                = Only Drush.
     *     DRUSH_BOOTSTRAP_DRUPAL_ROOT          = Find a valid Drupal root.
     *     DRUSH_BOOTSTRAP_DRUPAL_SITE          = Find a valid Drupal site.
     *     DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION = Load the site's settings.
     *     DRUSH_BOOTSTRAP_DRUPAL_DATABASE      = Initialize the database.
     *     DRUSH_BOOTSTRAP_DRUPAL_FULL          = Initialize Drupal fully.
     *
     * The value is the name of the method of the Boot class to
     * execute when bootstrapping.  Prior to bootstrapping, a "validate"
     * method is called, if defined.  The validate method name is the
     * bootstrap method name with "_validate" appended.
     */
    public function bootstrapPhases(): array
    {
        return [
            DrupalBootLevels::NONE            => 'NoLongerUsed',
            DrupalBootLevels::ROOT            => 'bootstrapDrupalRoot',
            DrupalBootLevels::SITE            => 'bootstrapDrupalSite',
            DrupalBootLevels::CONFIGURATION   => 'bootstrapDrupalConfiguration',
            DrupalBootLevels::DATABASE        => 'bootstrapDrupalDatabase',
            DrupalBootLevels::FULL            => 'bootstrapDrupalFull',
        ];
    }

    public function bootstrapPhaseMap(): array
    {
        return parent::bootstrapPhaseMap() + [
            'root' => DrupalBootLevels::ROOT,
            'site' => DrupalBootLevels::SITE,
            'config' => DrupalBootLevels::CONFIGURATION,
            'configuration' => DrupalBootLevels::CONFIGURATION,
            'db' => DrupalBootLevels::DATABASE,
            'database' => DrupalBootLevels::DATABASE,
            'full' => DrupalBootLevels::FULL,
        ];
    }

    /**
     * Validate the DRUSH_BOOTSTRAP_DRUPAL_ROOT phase.
     *
     * In this function, we will check if a valid Drupal directory is available.
     */
    public function bootstrapDrupalRootValidate(BootstrapManager $manager): bool
    {
        $drupal_root = $manager->getRoot();
        return (bool) $drupal_root;
    }

    /**
     * Bootstrap Drush with a valid Drupal Directory.
     *
     * In this function, the pwd will be moved to the root
     * of the Drupal installation.
     *
     * We also now load the drush.yml for this specific Drupal site.
     * We can now include files from the Drupal tree, and figure
     * out more context about the codebase, such as the version of Drupal.
     */
    public function bootstrapDrupalRoot(BootstrapManager $manager): void
    {
        $drupal_root = $manager->getRoot();
        chdir($drupal_root);
        $this->logger->info(dt("Change working directory to !drupal_root", ['!drupal_root' => $drupal_root]));

        $core = $this->bootstrapDrupalCore($manager, $drupal_root);

        // Make sure we are not bootstrapping twice
        if (defined('DRUSH_DRUPAL_CORE')) {
            if (DRUSH_DRUPAL_CORE != $core) {
                $this->logger->warning('Attempted to redefine DRUSH_DRUPAL_CORE. Original value: ' . DRUSH_DRUPAL_CORE . '; new value: ' . $core);
            }
            return;
        }

        // DRUSH_DRUPAL_CORE should point to the /core folder in Drupal 8+.
        define('DRUSH_DRUPAL_CORE', $core);

        $this->logger->info(dt("Initialized Drupal !version root directory at !drupal_root", ["!version" => Drush::bootstrap()->getVersion($drupal_root), '!drupal_root' => $drupal_root]));
    }

    /**
     * VALIDATE the DRUSH_BOOTSTRAP_DRUPAL_SITE phase.
     *
     * In this function we determine the URL used for the command,
     * and check for a valid settings.php file.
     */
    public function bootstrapDrupalSiteValidate(BootstrapManager $manager)
    {
    }

    /**
     * Initialize a site on the Drupal root.
     *
     * We now set various contexts that we determined and confirmed to be valid.
     * Additionally we load an optional drush.yml file in the site directory.
     */
    public function bootstrapDrupalSite(BootstrapManager $manager)
    {
        $this->bootstrapDoDrupalSite($manager);
    }

    /**
     * Initialize and load the Drupal configuration files.
     */
    public function bootstrapDrupalConfiguration(BootstrapManager $manager)
    {
    }

    /**
     * Validate the DRUSH_BOOTSTRAP_DRUPAL_DATABASE phase
     *
     * Attempt to make a working database connection using the
     * database credentials that were loaded during the previous
     * phase.
     */
    public function bootstrapDrupalDatabaseValidate(BootstrapManager $manager)
    {
    }

    /**
     * Bootstrap the Drupal database.
     */
    public function bootstrapDrupalDatabase(BootstrapManager $manager): void
    {
        // We presume that our derived classes will connect and then
        // either fail, or call us via parent::
        $this->logger->info(dt("Successfully connected to the Drupal database."));
    }

    /**
     * Attempt to load the full Drupal system.
     */
    public function bootstrapDrupalFull(BootstrapManager $manager): void
    {
    }
}
