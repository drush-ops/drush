<?php

namespace Drush\Boot;

use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Sql\SqlBase;
use Webmozart\PathUtil\Path;

abstract class DrupalBoot extends BaseBoot
{
    /**
     * Select the best URI for the provided cwd. Only called
     * if the user did not explicitly specify a URI.
     */
    public function findUri($root, $cwd)
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
            if ($scan == $root) {
                return false;
            }
        }
        return false;
    }

    public function validRoot($path)
    {
    }

    public function getVersion($drupal_root)
    {
    }

    public function confPath($require_settings = true, $reset = false)
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
    public function bootstrapPhases()
    {
        return parent::bootstrapPhases() + [
            DRUSH_BOOTSTRAP_DRUPAL_ROOT            => 'bootstrapDrupalRoot',
            DRUSH_BOOTSTRAP_DRUPAL_SITE            => 'bootstrapDrupalSite',
            DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION   => 'bootstrapDrupalConfiguration',
            DRUSH_BOOTSTRAP_DRUPAL_DATABASE        => 'bootstrapDrupalDatabase',
            DRUSH_BOOTSTRAP_DRUPAL_FULL            => 'bootstrapDrupalFull',
        ];
    }

    public function bootstrapPhaseMap()
    {
        return parent::bootstrapPhaseMap() + [
            'root' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
            'site' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
            'config' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
            'configuration' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
            'db' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
            'database' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
            'full' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
        ];
    }

    /**
     * Validate the DRUSH_BOOTSTRAP_DRUPAL_ROOT phase.
     *
     * In this function, we will check if a valid Drupal directory is available.
     */
    public function bootstrapDrupalRootValidate()
    {
        $drupal_root = Drush::bootstrapManager()->getRoot();
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
    public function bootstrapDrupalRoot()
    {

        $drupal_root = Drush::bootstrapManager()->getRoot();
        chdir($drupal_root);
        $this->logger->log(LogLevel::BOOTSTRAP, dt("Change working directory to !drupal_root", ['!drupal_root' => $drupal_root]));

        $core = $this->bootstrapDrupalCore($drupal_root);

        // DRUSH_DRUPAL_CORE should point to the /core folder in Drupal 8+.
        define('DRUSH_DRUPAL_CORE', $core);

        $this->logger->log(LogLevel::BOOTSTRAP, dt("Initialized Drupal !version root directory at !drupal_root", ["!version" => Drush::bootstrap()->getVersion($drupal_root), '!drupal_root' => $drupal_root]));
    }

    /**
     * VALIDATE the DRUSH_BOOTSTRAP_DRUPAL_SITE phase.
     *
     * In this function we determine the URL used for the command,
     * and check for a valid settings.php file.
     */
    public function bootstrapDrupalSiteValidate()
    {
    }

    /**
     * Initialize a site on the Drupal root.
     *
     * We now set various contexts that we determined and confirmed to be valid.
     * Additionally we load an optional drush.yml file in the site directory.
     */
    public function bootstrapDrupalSite()
    {
        $this->bootstrapDoDrupalSite();
    }

    /**
     * Initialize and load the Drupal configuration files.
     */
    public function bootstrapDrupalConfiguration()
    {
    }

    /**
     * Validate the DRUSH_BOOTSTRAP_DRUPAL_DATABASE phase
     *
     * Attempt to make a working database connection using the
     * database credentials that were loaded during the previous
     * phase.
     */
    public function bootstrapDrupalDatabaseValidate()
    {
        // Drupal requires PDO, and Drush requires php 5.6+ which ships with PDO
        // but PHP may be compiled with --disable-pdo.
        if (!class_exists('\PDO')) {
            $this->logger->log(LogLevel::BOOTSTRAP, dt('PDO support is required.'));
            return false;
        }
        try {
            $sql = SqlBase::create();
            // Drush requires a database client program during its Drupal bootstrap.
            $command = $sql->command();
            if (drush_program_exists($command) === false) {
                $this->logger->warning(dt('The command \'!command\' is required for preflight but cannot be found. Please install it and retry.', ['!command' => $command]));
                return false;
            }
            if (!$sql->query('SELECT 1;', null, drush_bit_bucket())) {
                $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
                $message .= dt("Hint: This may occur when Drush is trying to:\n");
                $message .= dt(" * bootstrap a site that has not been installed or does not have a configured database. In this case you can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line. See `drush topic docs-aliases` for details.\n");
                $message .= dt(" * connect the database through a socket. The socket file may be wrong or the php-cli may have no access to it in a jailed shell. See http://drupal.org/node/1428638 for details.\n");
                $message .= dt('More information may be available by running `drush status`');
                $this->logger->log(LogLevel::BOOTSTRAP, $message);
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::DEBUG, dt('Unable to validate DB: @e', ['@e' => $e->getMessage()]));
            return false;
        }
        return true;
    }

    /**
     * Test to see if the Drupal database has a specified
     * table or tables.
     *
     * This is a bootstrap helper function designed to be called
     * from the bootstrapDrupalDatabaseValidate() methods of
     * derived DrupalBoot classes.  If a database exists, but is
     * empty, then the Drupal database bootstrap will fail.  To
     * prevent this situation, we test for some table that is needed
     * in an ordinary bootstrap, and return FALSE from the validate
     * function if it does not exist, so that we do not attempt to
     * start the database bootstrap.
     *
     * Note that we must manually do our own prefix testing here,
     * because the existing wrappers we have for handling prefixes
     * depend on bootstrapping to the "database" phase, and therefore
     * are not available to validate this same phase.
     *
     * @param $required_tables
     *   Array of table names, or string with one table name
     *
     * @return TRUE if all required tables exist in the database.
     */
    public function bootstrapDrupalDatabaseHasTable($required_tables)
    {

        $sql = SqlBase::create();
        $spec = $sql->getDbSpec();
        $prefix = isset($spec['prefix']) ? $spec['prefix'] : null;
        if (!is_array($prefix)) {
            $prefix = ['default' => $prefix];
        }
        foreach ((array)$required_tables as $required_table) {
            $prefix_key = array_key_exists($required_table, $prefix) ? $required_table : 'default';
            $table_name = $prefix[$prefix_key] . $required_table;
            if (!$sql->alwaysQuery("SELECT 1 FROM $table_name LIMIT 1;", null, drush_bit_bucket())) {
                $this->logger->notice('Missing database table: '. $table_name);
                return false;
            }
        }
        return true;
    }

    /**
     * Bootstrap the Drupal database.
     */
    public function bootstrapDrupalDatabase()
    {
        // We presume that our derived classes will connect and then
        // either fail, or call us via parent::
        $this->logger->log(LogLevel::BOOTSTRAP, dt("Successfully connected to the Drupal database."));
    }

    /**
     * Attempt to load the full Drupal system.
     */
    public function bootstrapDrupalFull()
    {
    }
}
