<?php

namespace Drush\Boot;

use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Sql\SqlBase;
use Psr\Log\LoggerInterface;
use Drupal\user\Entity\User;

abstract class DrupalBoot extends BaseBoot
{

    public function validRoot($path)
    {
    }

    public function getVersion($drupal_root)
    {
    }

    public function getProfile()
    {
    }

    public function confPath($require_settings = true, $reset = false)
    {
        return confPath($require_settings, $reset);
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
     *     DRUSH_BOOTSTRAP_DRUPAL_LOGIN         = Log into Drupal with a valid user.
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
     * List of bootstrap phases where Drush should stop and look for commandfiles.
     *
     * For Drupal, we try at these bootstrap phases:
     *
     *   - Drush preflight: to find commandfiles in any system location,
     *     out of a Drupal installation.
     *   - Drupal root: to find commandfiles based on Drupal core version.
     *   - Drupal full: to find commandfiles defined within a Drupal directory.
     *
     * Once a command is found, Drush will ensure a bootstrap to the phase
     * declared by the command.
     *
     * @return array of PHASE indexes.
     */
    public function bootstrapInitPhases()
    {
        return array(DRUSH_BOOTSTRAP_DRUSH, DRUSH_BOOTSTRAP_DRUPAL_ROOT, DRUSH_BOOTSTRAP_DRUPAL_FULL);
    }

    public function enforceRequirement(&$command)
    {
        parent::enforceRequirement($command);
        $this->drushEnforceRequirementDrupalDependencies($command);
    }

    public function reportCommandError($command)
    {
        // If we reach this point, command doesn't fit requirements or we have not
        // found either a valid or matching command.

        // If no command was found check if it belongs to a disabled module.
        if (!$command) {
            $command = $this->drushCommandBelongsToDisabledModule();
        }
        parent::reportCommandError($command);
    }

    public function commandDefaults()
    {
        return array(
        'drupal dependencies' => array(),
        'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
        );
    }

    /**
     * @return array of strings - paths to directories where contrib
     * modules can be found
     */
    abstract public function contribModulesPaths();

    /**
     * @return array of strings - paths to directories where contrib
     * themes can be found
     */
    abstract public function contribThemesPaths();

    public function commandfileSearchpaths($phase, $phase_max = false)
    {
        if (!$phase_max) {
            $phase_max = $phase;
        }

        $container = Drush::getContainer();
        $discovery = $container->get('commandDiscovery');
        $commandFiles = [];
        $searchpath = [];
        switch ($phase) {
            case DRUSH_BOOTSTRAP_DRUPAL_ROOT:
                $drupal_root = Drush::bootstrapManager()->getRoot();
                $searchpath[] = $drupal_root . '/../drush';
                $searchpath[] = $drupal_root . '/drush';
                $searchpath[] = $drupal_root . '/sites/all/drush';
                $commandFiles = $discovery->discover($searchpath, '\Drupal');
                break;
            case DRUSH_BOOTSTRAP_DRUPAL_SITE:
                // If we are going to stop bootstrapping at the site, then
                // we will quickly add all commandfiles that we can find for
                // any extension associated with the site, whether it is enabled
                // or not.  If we are, however, going to continue on to bootstrap
                // all the way to DRUSH_BOOTSTRAP_DRUPAL_FULL, then we will
                // instead wait for that phase, which will more carefully add
                // only those Drush commandfiles that are associated with
                // enabled modules.
                if ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
                    $searchpath = array_merge($searchpath, $this->contribModulesPaths());

                    // Adding commandfiles located within /profiles. Try to limit to one profile for speed. Note
                    // that Drupal allows enabling modules from a non-active profile so this logic is kinda dodgy.
                    $cid = drush_cid_install_profile();
                    if ($cached = drush_cache_get($cid)) {
                        $profile = $cached->data;
                        $searchpath[] = "profiles/$profile/modules";
                        $searchpath[] = "profiles/$profile/themes";
                    } else {
                        // If install_profile is not available, scan all profiles.
                        $searchpath[] = "profiles";
                        $searchpath[] = "sites/all/profiles";
                    }

                    $searchpath = array_merge($searchpath, $this->contribThemesPaths());
                    // Drupal 8 uses the modules' services files to find commandfiles. Should we allow
                    // redundant find-module-by-location for Drupal 8?  (Maybe not.)
                    if (drush_drupal_major_version() < 8) {
                        $commandFiles = $discovery->discoverNamespaced($searchpath, '\Drupal');
                    }
                }
                break;
            case DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION:
                // Nothing to do here anymore. Left for documentation.
                break;
            case DRUSH_BOOTSTRAP_DRUPAL_FULL:
                // Add enabled module paths, excluding the install profile. Since we are bootstrapped,
                // we can use the Drupal API.
                $ignored_modules = drush_get_option_list('ignored-modules', array());
                $modules = array_keys(\Drupal::moduleHandler()->getModuleList());
                $module_list = array_combine($modules, $modules);
                $cid = drush_cid_install_profile();
                if ($cached = drush_cache_get($cid)) {
                    $ignored_modules[] = $cached->data;
                }
                foreach (array_diff($module_list, $ignored_modules) as $module) {
                    $filepath = drupal_get_path('module', $module);
                    if ($filepath && $filepath != '/') {
                        $searchpath[] = $filepath;
                    }
                }

                // Check all enabled themes including non-default and non-admin.
                foreach (\Drupal::service('theme_handler')->listInfo() as $key => $value) {
                    $searchpath[] = drupal_get_path('theme', $key);
                }
                // Drupal 8 uses the modules' services files to find commandfiles. Should we allow
                // redundant find-module-by-location for Drupal 8?  (Maybe not.)
                // The operator below was wrong anyway.
        //        if (drush_drupal_major_version() < 8) {
        //          $commandFiles = $discovery->discoverNamespaced($searchpath, '\Drupal');
        //        }
                break;
        }
        // A little inelegant, but will do for now.
        drush_init_register_command_files($container, $commandFiles);

        return $searchpath;
    }

    /**
     * Check if the given command belongs to a disabled module.
     *
     * @return array
     *   Array with a command-like bootstrap error or FALSE if Drupal was not
     *   bootstrapped fully or the command does not belong to a disabled module.
     */
    public function drushCommandBelongsToDisabledModule()
    {
        if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
            _drush_find_commandfiles(DRUSH_BOOTSTRAP_DRUPAL_SITE, DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
            drush_get_commands(true);
            $commands = drush_get_commands();
            $arguments = drush_get_arguments();
            $command_name = array_shift($arguments);
            if (isset($commands[$command_name])) {
                // We found it. Load its module name and set an error.
                if (is_array($commands[$command_name]['drupal dependencies']) && count($commands[$command_name]['drupal dependencies'])) {
                    $modules = implode(', ', $commands[$command_name]['drupal dependencies']);
                } else {
                    // The command does not define Drupal dependencies. Derive them.
                    $command_files = commandfiles_cache()->get();
                    $command_path = $commands[$command_name]['path'] . DIRECTORY_SEPARATOR . $commands[$command_name]['commandfile'] . '.drush.inc';
                    $modules = array_search($command_path, $command_files);
                }
                return array(
                'bootstrap_errors' => array(
                  'DRUSH_COMMAND_DEPENDENCY_ERROR' => dt('Command !command needs the following extension(s) enabled to run: !dependencies.', array(
                    '!command' => $command_name,
                    '!dependencies' => $modules,
                  )),
                ),
                );
            }
        }

        return false;
    }

    /**
     * Check that a command has its declared dependencies available or have no
     * dependencies.
     *
     * @param $command
     *   Command to check. Any errors  will be added to the 'bootstrap_errors' element.
     *
     * @return
     *   TRUE if command is valid.
     */
    public function drushEnforceRequirementDrupalDependencies(&$command)
    {
        // If the command bootstrap is DRUSH_BOOTSTRAP_MAX, then we will
        // allow the requirements to pass if we have not successfully
        // bootstrapped Drupal.  The combination of DRUSH_BOOTSTRAP_MAX
        // and 'drupal dependencies' indicates that the drush command
        // will use the dependent modules only if they are available.
        if ($command['bootstrap'] == DRUSH_BOOTSTRAP_MAX) {
            // If we have not bootstrapped, then let the dependencies pass;
            // if we have bootstrapped, then enforce them.
            if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
                return true;
            }
        }
        // If there are no drupal dependencies, then do nothing
        if (!empty($command['drupal dependencies'])) {
            foreach ($command['drupal dependencies'] as $dependency) {
                if (!\Drupal::moduleHandler()->moduleExists($dependency)) {
                    $command['bootstrap_errors']['DRUSH_COMMAND_DEPENDENCY_ERROR'] = dt('Command !command needs the following modules installed/enabled to run: !dependencies.', array('!command' => $command['command'], '!dependencies' => implode(', ', $command['drupal dependencies'])));
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Validate the DRUSH_BOOTSTRAP_DRUPAL_ROOT phase.
     *
     * In this function, we will check if a valid Drupal directory is available.
     * We also determine the value that will be stored in the DRUSH_DRUPAL_ROOT
     * context and DRUPAL_ROOT constant if it is considered a valid option.
     */
    public function bootstrapDrupalRootValidate()
    {
        $drupal_root = Drush::bootstrapManager()->getRoot();

        if (empty($drupal_root)) {
            return drush_bootstrap_error('DRUSH_NO_DRUPAL_ROOT', dt("A Drupal installation directory could not be found"));
        }
        if (!$signature = drush_valid_root($drupal_root)) {
            return drush_bootstrap_error('DRUSH_INVALID_DRUPAL_ROOT', dt("The directory !drupal_root does not contain a valid Drupal installation", array('!drupal_root' => $drupal_root)));
        }

        $version = drush_drupal_version($drupal_root);
        $major_version = drush_drupal_major_version($drupal_root);
        if ($major_version <= 6) {
            return drush_set_error('DRUSH_DRUPAL_VERSION_UNSUPPORTED', dt('Drush !drush_version does not support Drupal !major_version.', array('!drush_version' => Drush::getMajorVersion(), '!major_version' => $major_version)));
        }

        drush_bootstrap_value('drupal_root', $drupal_root);
        define('DRUSH_DRUPAL_SIGNATURE', $signature);

        return true;
    }

    /**
     * Bootstrap Drush with a valid Drupal Directory.
     *
     * In this function, the pwd will be moved to the root
     * of the Drupal installation.
     *
     * The DRUSH_DRUPAL_ROOT context, DRUSH_DRUPAL_CORE context, DRUPAL_ROOT, and the
     * DRUSH_DRUPAL_CORE constants are populated from the value that we determined during
     * the validation phase.
     *
     * We also now load the drushrc.php for this specific Drupal site.
     * We can now include files from the Drupal Tree, and figure
     * out more context about the platform, such as the version of Drupal.
     */
    public function bootstrapDrupalRoot()
    {
        // Load the config options from Drupal's /drush and sites/all/drush directories.
        drush_load_config('drupal');

        $drupal_root = drush_set_context('DRUSH_DRUPAL_ROOT', drush_bootstrap_value('drupal_root'));
        chdir($drupal_root);
        $this->logger->log(LogLevel::BOOTSTRAP, dt("Change working directory to !drupal_root", array('!drupal_root' => $drupal_root)));
        $version = drush_drupal_version();
        $major_version = drush_drupal_major_version();

        $core = $this->bootstrapDrupalCore($drupal_root);

        // DRUSH_DRUPAL_CORE should point to the /core folder in Drupal 8+ or to DRUPAL_ROOT
        // in prior versions.
        drush_set_context('DRUSH_DRUPAL_CORE', $core);
        define('DRUSH_DRUPAL_CORE', $core);

        $this->logger->log(LogLevel::BOOTSTRAP, dt("Initialized Drupal !version root directory at !drupal_root", array("!version" => $version, '!drupal_root' => $drupal_root)));
    }

    /**
     * VALIDATE the DRUSH_BOOTSTRAP_DRUPAL_SITE phase.
     *
     * In this function we determine the URL used for the command,
     * and check for a valid settings.php file.
     *
     * To do this, we need to set up the $_SERVER environment variable,
     * to allow us to use confPath to determine what Drupal will load
     * as a configuration file.
     */
    public function bootstrapDrupalSiteValidate()
    {
        // TODO: uri not injected in traditional Drush dispatcher. This is not needed for Symfony Dispatch.
        if (!$this->uri) {
            $this->uri = _drush_bootstrap_selected_uri();
        }
        drush_set_context('DRUSH_SELECTED_DRUPAL_SITE_CONF_PATH', drush_conf_path($this->uri));

        $this->bootstrapDrupalSiteSetupServerGlobal();
        $site = drush_bootstrap_value('site', $_SERVER['HTTP_HOST']);
        $confPath = drush_bootstrap_value('confPath', $this->confPath(true, true));
        return true; //$this->bootstrapDrupalSiteValidate_settings_present();
    }

    /**
     * Set up the $_SERVER globals so that Drupal will see the same values
     * that it does when serving pages via the web server.
     */
    public function bootstrapDrupalSiteSetupServerGlobal()
    {
        // Fake the necessary HTTP headers that Drupal needs:
        if ($this->uri) {
            $drupal_base_url = parse_url($this->uri);
            // If there's no url scheme set, add http:// and re-parse the url
            // so the host and path values are set accurately.
            if (!array_key_exists('scheme', $drupal_base_url)) {
                $drush_uri = 'http://' . $this->uri;
                $drupal_base_url = parse_url($drush_uri);
            }
            // Fill in defaults.
            $drupal_base_url += array(
            'path' => '',
            'host' => null,
            'port' => null,
            );
            $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];

            if ($drupal_base_url['scheme'] == 'https') {
                  $_SERVER['HTTPS'] = 'on';
            }

            if ($drupal_base_url['port']) {
                  $_SERVER['HTTP_HOST'] .= ':' . $drupal_base_url['port'];
            }
            $_SERVER['SERVER_PORT'] = $drupal_base_url['port'];

            $_SERVER['REQUEST_URI'] = $drupal_base_url['path'] . '/';
        } else {
            $_SERVER['HTTP_HOST'] = 'default';
            $_SERVER['REQUEST_URI'] = '/';
        }

        $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'] . 'index.php';
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD']  = 'GET';

        $_SERVER['SERVER_SOFTWARE'] = null;
        $_SERVER['HTTP_USER_AGENT'] = null;
        $_SERVER['SCRIPT_FILENAME'] = DRUPAL_ROOT . '/index.php';
    }

    /**
     * Validate that the Drupal site has all of the settings that it
     * needs to operated.
     */
//  public function bootstrapDrupalSiteValidate_settings_present() {
//    $site = drush_bootstrap_value('site', $_SERVER['HTTP_HOST']);
//
//    $confPath = drush_bootstrap_value('confPath', $this->confPath(TRUE, TRUE));
//    $conf_file = "$confPath/settings.php";
//    if (!file_exists($conf_file)) {
//      $this->logger()->notice(dt("Could not find a Drupal settings.php file at !file.",
//        array('!file' => $conf_file)));
//      return FALSE;
//      // return drush_bootstrap_error('DRUPAL_SITE_SETTINGS_NOT_FOUND', );
//    }
//
//    return TRUE;
//  }

    /**
     * Called by bootstrapDrupalSite to do the main work
     * of the drush drupal site bootstrap.
     */
    public function bootstrapDoDrupalSite()
    {
        drush_set_context('DRUSH_URI', $this->uri);
        $site = drush_set_context('DRUSH_DRUPAL_SITE', drush_bootstrap_value('site'));
        $confPath = drush_set_context('DRUSH_DRUPAL_SITE_ROOT', drush_bootstrap_value('confPath'));

        $this->logger->log(LogLevel::BOOTSTRAP, dt("Initialized Drupal site !site at !site_root", array('!site' => $site, '!site_root' => $confPath)));
    }

    /**
     * Initialize a site on the Drupal root.
     *
     * We now set various contexts that we determined and confirmed to be valid.
     * Additionally we load an optional drushrc.php file in the site directory.
     */
    public function bootstrapDrupalSite()
    {
        drush_load_config('site');
        $this->bootstrapDoDrupalSite();
    }

    /**
     * Initialize and load the Drupal configuration files.
     *
     * We process and store a normalized set of database credentials
     * from the loaded configuration file, so we can validate them
     * and access them easily in the future.
     *
     * Also override Drupal variables as per --variables option.
     */
    public function bootstrapDrupalConfiguration()
    {
        global $conf;

        $current_override = \Drush\Drush::config()->get('variables');
        $current_override = explode(',', $current_override);
        foreach ($current_override as $name => $value) {
            if (is_numeric($name) && (strpos($value, '=') !== false)) {
                list($name, $value) = explode('=', $value, 2);
            }
            $override[$name] = $value;
        }
        $conf = is_array($conf) ? array_merge($conf, $override) : $conf;
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
                $this->logger->log(LogLevel::BOOTSTRAP, dt('The command \'!command\' is required for preflight but cannot be found. Please install it and retry.', ['!command' => $command]));
                return false;
            }
            if (!$sql->query('SELECT 1;')) {
                $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
                $message .= dt("Hint: This may occur when Drush is trying to:\n");
                $message .= dt(" * bootstrap a site that has not been installed or does not have a configured database. In this case you can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line. See `drush topic docs-aliases` for details.\n");
                $message .= dt(" * connect the database through a socket. The socket file may be wrong or the php-cli may have no access to it in a jailed shell. See http://drupal.org/node/1428638 for details.\n");
                $message .= dt('More information may be available by running `drush status`');
                $this->logger->log(LogLevel::BOOTSTRAP, $message);
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::DEBUG, dt('Unable to validate DB: @e', array('@e' => $e->getMessage())));
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
     * @return TRUE if all tables in input parameter exist in
     *   the database.
     */
    public function bootstrapDrupalDatabaseHasTable($required_tables)
    {
        try {
            $sql = SqlBase::create();
            $spec = $sql->getDbSpec();
            $prefix = isset($spec['prefix']) ? $spec['prefix'] : null;
            if (!is_array($prefix)) {
                $prefix = array('default' => $prefix);
            }
            $tables = $sql->listTables();
            if (!$tables) {
                return false;
            }
            foreach ((array)$required_tables as $required_table) {
                $prefix_key = array_key_exists($required_table, $prefix) ? $required_table : 'default';
                if (!in_array($prefix[$prefix_key] . $required_table, $tables)) {
                    return false;
                }
            }
        } catch (Exception $e) {
            // Usually the checks above should return a result without
            // throwing an exception, but we'll catch any that are
            // thrown just in case.
            return false;
        }
        return true;
    }

    /**
     * Boostrap the Drupal database.
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

        $this->addLogger();

        // Write correct install_profile to cache as needed. Used by _drush_find_commandfiles().
        $cid = drush_cid_install_profile();
        $install_profile = $this->getProfile();
        if ($cached_install_profile = drush_cache_get($cid)) {
            // We have a cached profile. Check it for correctness and save new value if needed.
            if ($cached_install_profile->data != $install_profile) {
                drush_cache_set($cid, $install_profile);
            }
        } else {
            // No cached entry so write to cache.
            drush_cache_set($cid, $install_profile);
        }

        _drush_log_drupal_messages();
    }
}
