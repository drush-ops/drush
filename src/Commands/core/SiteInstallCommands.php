<?php
namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\ConnectionNotDefinedException;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Log\LogLevel;
use Drupal\Core\Config\FileStorage;
use Drush\SiteAlias\SiteAliasManager;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Sql\SqlBase;
use Drush\Utils\StringUtils;
use Webmozart\PathUtil\Path;

class SiteInstallCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Install Drupal along with modules/themes/configuration/profile.
     *
     * @command site:install
     * @param $profile An install profile name. Defaults to 'standard' unless an install profile is marked as a distribution. Additional info for the install profile may also be provided with additional arguments. The key is in the form [form name].[parameter name]
     * @option db-url A Drupal 6 style database URL. Required for initial install, not re-install. If omitted and required, Drush prompts for this item.
     * @option db-prefix An optional table prefix to use for initial install.
     * @option db-su Account to use when creating a new database. Must have Grant permission (mysql only). Optional.
     * @option db-su-pw Password for the "db-su" account. Optional.
     * @option account-name uid1 name. Defaults to admin
     * @option account-pass uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in config.yml.
     * @option account-mail uid1 email. Defaults to admin@example.com
     * @option locale A short language code. Sets the default site language. Language files must already be present.
     * @option site-name Defaults to Site-Install
     * @option site-mail From: for system mailings. Defaults to admin@example.com
     * @option sites-subdir Name of directory under 'sites' which should be created.
     * @option config-dir A path pointing to a full set of configuration which should be imported after installation.
     * @usage drush si expert --locale=uk
     *   (Re)install using the expert install profile. Set default language to Ukrainian.
     * @usage drush si --db-url=mysql://root:pass@localhost:port/dbname
     *   Install using the specified DB params.
     * @usage drush si --db-url=sqlite://sites/example.com/files/.ht.sqlite
     *   Install using SQLite
     * @usage drush si --account-pass=mom
     *   Re-install with specified uid1 password.
     * @usage drush si standard install_configure_form.enable_update_status_emails=NULL
     *   Disable email notification during install and later. If your server has no mail transfer agent, this gets rid of an error during install.
     * @bootstrap root
     * @kernel installer
     * @aliases si,sin,site-install
     *
     */
    public function install(array $profile, $options = ['db-url' => self::REQ, 'db-prefix' => self::REQ, 'db-su' => self::REQ, 'db-su-pw' => self::REQ, 'account-name' => 'admin', 'account-mail' => 'admin@example.com', 'site-mail' => 'admin@example.com', 'account-pass' => self::REQ, 'locale' => 'en', 'site-name' => 'Drush Site-Install', 'site-pass' => self::REQ, 'sites-subdir' => self::REQ, 'config-dir' => self::REQ])
    {
        $additional = $profile;
        $profile = array_shift($additional) ?: '';
        $form_options = [];
        foreach ((array)$additional as $arg) {
            list($key, $value) = explode('=', $arg, 2);

            // Allow for numeric and NULL values to be passed in.
            if (is_numeric($value)) {
                $value = intval($value);
            } elseif ($value == 'NULL') {
                $value = null;
            }

            $form_options[$key] = $value;
        }

        $this->serverGlobals(Drush::bootstrapManager()->getUri());
        $class_loader = Drush::service('loader');
        $profile = $this->determineProfile($profile, $options, $class_loader);

        $sql = SqlBase::create($options);
        $db_spec = $sql->getDbSpec();

        $account_pass = $options['account-pass'] ?: StringUtils::generatePassword();
        $settings = [
            'parameters' => [
                'profile' => $profile,
                'langcode' => $options['locale'],
            ],
            'forms' => [
                'install_settings_form' => [
                    'driver' => $db_spec['driver'],
                    $db_spec['driver'] => $db_spec,
                    'op' => dt('Save and continue'),
                ],
                'install_configure_form' => [
                    'site_name' => $options['site-name'],
                    'site_mail' => $options['site-mail'],
                    'account' => [
                      'name' => $options['account-name'],
                      'mail' => $options['account-mail'],
                      'pass' => [
                        'pass1' => $account_pass,
                        'pass2' => $account_pass,
                      ],
                    ],
                    'enable_update_status_module' => true,
                    'enable_update_status_emails' => true,
                    'clean_url' => true,
                    'op' => dt('Save and continue'),
                ],
            ],
        ];

        // Merge in the additional options.
        foreach ($form_options as $key => $value) {
            $current = &$settings['forms'];
            foreach (explode('.', $key) as $param) {
                $current = &$current[$param];
            }
            $current = $value;
        }

        $msg = 'Starting Drupal installation. This takes a while.';
        if (is_null($options['notify'])) {
            $msg .= ' Consider using the --notify global option.';
        }
        $this->logger()->notice(dt($msg));

        // Define some functions which alter away the install_finished task.
        require_once Path::join(DRUSH_BASE_PATH, 'includes/site_install.inc');

        require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';
        drush_op('install_drupal', $class_loader, $settings);
        if (empty($options['account-pass'])) {
            $this->logger()->success(dt('Installation complete.  User name: @name  User password: @pass', ['@name' => $options['account-name'], '@pass' => $account_pass]));
        } else {
            $this->logger()->success(dt('Installation complete.'));
        }
    }

    protected function determineProfile($profile, $options, $class_loader)
    {
        // --config-dir fails with Standard profile and any other one that carries content entities.
        // Force to minimal install profile.
        if ($options['config-dir']) {
            $this->logger()->info(dt("Using 'minimal' install profile since --config-dir option was provided."));
            $profile = 'minimal';
        }
        if (empty($profile)) {
            $boot = Drush::bootstrap();
            $profile = $boot->getKernel()->getInstallProfile();
        }

        if (empty($profile)) {
            // If there is an installation profile that acts as a distribution, use it.
            // You can turn your installation profile into a distribution by providing a
            // @code
            //   distribution:
            //     name: 'Distribution name'
            // @endcode
            // block in the profile's info YAML file.
            // See https://www.drupal.org/node/2210443 for more information.
            require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';
            $install_state = ['interactive' => false] + install_state_defaults();
            try {
                install_begin_request($class_loader, $install_state);
                $profile = _install_select_profile($install_state);
            } catch (\Exception $e) {
                // This is only a best effort to provide a better default, no harm done
                // if it fails.
            }
        }
        if (empty($profile)) {
            $profile = 'standard';
        }
        return $profile;
    }

    /**
     * Post installation, run the configuration import.
     *
     * @hook post-command site-install
     */
    public function post($result, CommandData $commandData)
    {
        if ($config = $commandData->input()->getOption('config-dir')) {
            // Set the destination site UUID to match the source UUID, to bypass a core fail-safe.
            $source_storage = new FileStorage($config);
            $options = ['yes' => true];
            drush_invoke_process('@self', 'config-set', ['system.site', 'uuid', $source_storage->read('system.site')['uuid']], $options);
            // Run a full configuration import.
            drush_invoke_process('@self', 'config-import', [], ['source' => $config] + $options);
        }
    }

    /**
     * Check to see if there are any .yml files in the provided config directory.
     */
    protected function hasConfigFiles($config)
    {
        $files = glob("$config/*.yml");
        return !empty($files);
    }

    /**
     * @hook validate site-install
     */
    public function validate(CommandData $commandData)
    {
        if ($sites_subdir = $commandData->input()->getOption('sites-subdir')) {
            $lower = strtolower($sites_subdir);
            if ($sites_subdir != $lower) {
                $this->logger()->warning(dt('Only lowercase sites-subdir are valid. Switching to !lower.', ['!lower' => $lower]));
                $commandData->input()->setOption('sites-subdir', $lower);
            }
            // Make sure that we will bootstrap to the 'sites-subdir' site.
            $bootstrapManager = Drush::bootstrapManager();
            $bootstrapManager->setUri('http://' . $sites_subdir);
        }

        if ($config = $commandData->input()->getOption('config-dir')) {
            if (!file_exists($config)) {
                throw new \Exception('The config source directory does not exist.');
            }
            if (!is_dir($config)) {
                throw new \Exception('The config source is not a directory.');
            }
            // Skip config import with a warning if specified config dir is empty.
            if (!$this->hasConfigFiles($config)) {
                $this->logger()->warning(dt('Configuration import directory @config does not contain any configuration; will skip import.', ['@config' => $config]));
                $commandData->input()->setOption('config-dir', '');
            }
        }

        try {
            // Get AnnotationData. @todo Find a better way.
            $annotationData = Drush::getApplication()->find('site:install')->getAnnotationData();
            Drush::bootstrapManager()->bootstrapMax(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION, $annotationData);
            $sql = SqlBase::create($commandData->input()->getOptions());
        } catch (\Exception $e) {
            // Ask questions to get our data.
            // TODO: we should only 'ask' in hook interact, never in hook validate
            if ($commandData->input()->getOption('db-url') == '') {
                // Prompt for the db-url data if it was not provided via --db-url.
                $database = $this->io()->ask('Database name', 'drupal');
                $driver = $this->io()->ask('Database driver', 'mysql');
                $username = $this->io()->ask('Database username', 'drupal');
                $password = $this->io()->ask('Database password', 'drupal');
                $host = $this->io()->ask('Database host', '127.0.0.1');
                $port = $this->io()->ask('Database port', '3306');
                $db_url = "$driver://$username:$password@$host:$port/$database";
                $commandData->input()->setOption('db-url', $db_url);

                try {
                    $sql = SqlBase::create($commandData->input()->getOptions());
                } catch (\Exception $e) {
                    throw new \Exception(dt('Could not determine database connection parameters. Pass --db-url option.'));
                }
            }
        }
    }

    /**
     * Perform setup tasks before installation.
     *
     * @hook pre-command site-install
     *
     */
    public function pre(CommandData $commandData)
    {
        $sql = SqlBase::create($commandData->input()->getOptions());
        $db_spec = $sql->getDbSpec();

        $aliasRecord = $this->siteAliasManager()->getSelf();
        $root = $aliasRecord->root();

        $dir = $commandData->input()->getOption('sites-subdir');
        if (!$dir) {
            // We will allow the 'uri' from the site alias to provide
            // a fallback name when '--sites-subdir' is not specified, but
            // only if the uri and the folder name match, and only if
            // the sites directory has already been created.
            $dir = $this->getSitesSubdirFromUri($root, $aliasRecord->get('uri'));
        }

        if (!$dir) {
            throw new \Exception(dt('Could not determine target sites directory for site to install. Use --sites-subdir to specify.'));
        }

        $sites_subdir = Path::join('sites', $dir);
        $confPath = $sites_subdir;
        $settingsfile = Path::join($confPath, 'settings.php');
        $sitesfile = "sites/sites.php";
        $default = realpath(Path::join($root, 'sites/default'));
        $sitesfile_write = realpath($confPath) != $default && !file_exists($sitesfile);

        if (!file_exists($settingsfile)) {
            $msg[] = dt('create a @settingsfile file', ['@settingsfile' => $settingsfile]);
        }
        if ($sitesfile_write) {
            $msg[] = dt('create a @sitesfile file', ['@sitesfile' => $sitesfile]);
        }
        if ($sql->dbExists()) {
            $msg[] = dt("DROP all tables in your '@db' database.", ['@db' => $db_spec['database']]);
        } else {
            $msg[] = dt("CREATE the '@db' database.", ['@db' => $db_spec['database']]);
        }

        if (!$this->io()->confirm(dt('You are about to ') . implode(dt(' and '), $msg) . ' Do you want to continue?')) {
            throw new UserAbortException();
        }

        // Can't install without sites subdirectory and settings.php.
        if (!file_exists($confPath)) {
            if (!drush_mkdir($confPath) && !Drush::simulate()) {
                throw new \Exception(dt('Failed to create directory @confPath', ['@confPath' => $confPath]));
            }
        } else {
            $this->logger()->info(dt('Sites directory @subdir already exists - proceeding.', ['@subdir' => $confPath]));
        }

        if (!drush_file_not_empty($settingsfile)) {
            if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile) && !Drush::simulate()) {
                throw new \Exception(dt('Failed to copy sites/default/default.settings.php to @settingsfile', ['@settingsfile' => $settingsfile]));
            }
        }

        // Write an empty sites.php if we using multi-site.
        if ($sitesfile_write) {
            if (!drush_op('copy', 'sites/example.sites.php', $sitesfile) && !Drush::simulate()) {
                throw new \Exception(dt('Failed to copy sites/example.sites.php to @sitesfile', ['@sitesfile' => $sitesfile]));
            }
        }

        // We need to be at least at DRUSH_BOOTSTRAP_DRUPAL_SITE to select the site uri to install to
        define('MAINTENANCE_MODE', 'install');
        $bootstrapManager = Drush::bootstrapManager();
        $bootstrapManager->doBootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE);

        if (!$sql->dropOrCreate()) {
            throw new \Exception(dt('Failed to create database: @error', ['@error' => implode(drush_shell_exec_output())]));
        }
    }

    /**
     * Determine an appropriate site subdir name to use for the
     * provided uri.
     */
    protected function getSitesSubdirFromUri($root, $uri)
    {
        $dir = strtolower($uri);
        // Always accept simple uris (e.g. 'dev', 'stage', etc.)
        if (preg_match('#^[a-z0-9_-]*$#', $dir)) {
            return $dir;
        }
        // Strip off the protocol from the provided uri -- however,
        // now we will require that the sites subdir already exist.
        $dir = preg_replace('#[^/]*/*#', '', $dir);
        if ($dir && file_exists(Path::join($root, $dir))) {
            return $dir;
        }
        // Find the dir from sites.php file
        $sites_file = $root . '/sites/sites.php';
        if (file_exists($sites_file)) {
            include $sites_file;
            if (array_key_exists($uri, $sites)) {
                return $sites[$uri];
            }
        }
        return false;
    }

    /**
     * Fake the necessary HTTP headers that the Drupal installer still needs:
     * @see https://github.com/drupal/drupal/blob/d260101f1ea8a6970df88d2f1899248985c499fc/core/includes/install.core.inc#L287
     */
    public function serverGlobals($drupal_base_url)
    {
        $drupal_base_url = parse_url($drupal_base_url);

        // Fill in defaults.
        $drupal_base_url += [
            'scheme' => null,
            'path' => '',
            'host' => null,
            'port' => null,
        ];
        $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];

        if ($drupal_base_url['scheme'] == 'https') {
              $_SERVER['HTTPS'] = 'on';
        }

        if ($drupal_base_url['port']) {
              $_SERVER['HTTP_HOST'] .= ':' . $drupal_base_url['port'];
        }
        $_SERVER['SERVER_PORT'] = $drupal_base_url['port'];

        $_SERVER['REQUEST_URI'] = $drupal_base_url['path'] . '/';

        $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'] . 'index.php';
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_METHOD']  = 'GET';

        $_SERVER['SERVER_SOFTWARE'] = null;
        $_SERVER['HTTP_USER_AGENT'] = null;
        $_SERVER['SCRIPT_FILENAME'] = DRUPAL_ROOT . '/index.php';
    }
}
