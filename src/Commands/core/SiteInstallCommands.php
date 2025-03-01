<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Composer\Autoload\ClassLoader;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Database\Database;
use Drupal\Core\Installer\Exception\AlreadyInstalledException;
use Drupal\Core\Installer\Exception\InstallerException;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Site\Settings;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Boot\Kernels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\Sql\SqlBase;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class SiteInstallCommands extends DrushCommands
{
    use AutowireTrait;
    use ExecTrait;

    const INSTALL = 'site:install';

    public function __construct(
        private readonly BootstrapManager $bootstrapManager,
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly ClassLoader $autoloader
    ) {
        parent::__construct();
    }

    /**
     * Install Drupal along with modules/themes/configuration/profile.
     */
    #[CLI\Command(name: self::INSTALL, aliases: ['si', 'sin', 'site-install'])]
    #[CLI\Argument(name: 'recipeOrProfile', description: 'An install profile name, or a path to a directory containing a recipe. Relative paths are searched relative to both the Drupal root and the cwd. Defaults to <info>standard</info> unless an install profile is marked as a distribution. Use <info>minimal</info> for a bare minimum installation. Additional info for the install profile may also be provided with additional arguments. Use the format <info>[form name].[parameter name]</info>')]
    #[CLI\Option(name: 'db-url', description: 'A Drupal 10 style database URL. Required for initial install, not re-install. If omitted and required, Drush prompts for this item.')]
    #[CLI\Option(name: 'db-prefix', description: 'An optional table prefix to use for initial install.')]
    #[CLI\Option(name: 'db-su', description: 'Account to use when creating a new database. Must have Grant permission (mysql only). Optional.')]
    #[CLI\Option(name: 'db-su-pw', description: 'Password for the <info>db-su</info> account. Optional.')]
    #[CLI\Option(name: 'account-name', description: 'uid1 name.')]
    #[CLI\Option(name: 'account-pass', description: 'uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in drush.yml.')]
    #[CLI\Option(name: 'account-mail', description: 'uid1 email.')]
    #[CLI\Option(name: 'locale', description: 'A short language code. Sets the default site language. Language files must already be present.')]
    #[CLI\Option(name: 'site-name', description: 'Site name')]
    #[CLI\Option(name: 'site-mail', description: '<info>From:</info> for system mailings.')]
    #[CLI\Option(name: 'sites-subdir', description: 'Name of directory under <info>sites</info> which should be created.')]
    #[CLI\Option(name: 'existing-config', description: 'Configuration from <info>sync</info> directory should be imported during installation.')]
    #[CLI\Usage(name: 'drush si demo_umami --locale=da', description: '(Re)install using the Umami install profile. Set default language to Danish.')]
    #[CLI\Usage(name: 'drush si --db-url=mysql://user:pass@localhost:port/dbname?module=mysql#tableprefix', description: 'Install on MySQL using the specified DB params.')]
    #[CLI\Usage(name: 'drush si --db-url=sqlite://sites/example.com/files/.ht.sqlite?module=sqlite#tableprefix', description: 'Install on SQLite, using database file <info>sites/example.com/files/.ht.sqlite</info>')]
    #[CLI\Usage(name: 'drush si --db-url=sqlite://:memory:?module=sqlite', description: 'Install using SQLite in-memory database, that is not persisted. Useful for testing.')]
    #[CLI\Usage(name: 'drush si --account-pass=mom', description: 'Re-install with specified uid1 password.')]
    #[CLI\Usage(name: 'drush si --existing-config', description: 'Install based on the yml files stored in the config export/import directory.')]
    #[CLI\Usage(name: 'drush si standard install_configure_form.enable_update_status_emails=NULL', description: 'Disable email notification during install and later. If your server has no mail transfer agent, this gets rid of an error during install.')]
    #[CLI\Usage(name: 'drush si core/recipes/standard', description: 'Install from the core Standard recipe.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::ROOT)]
    #[CLI\Kernel(name: Kernels::INSTALLER)]
    public function install(array $recipeOrProfile, $options = ['db-url' => self::REQ, 'db-prefix' => self::REQ, 'db-su' => self::REQ, 'db-su-pw' => self::REQ, 'account-name' => 'admin', 'account-mail' => 'admin@example.com', 'site-mail' => 'admin@example.com', 'account-pass' => self::REQ, 'locale' => 'en', 'site-name' => 'Drush Site-Install', 'site-pass' => self::REQ, 'sites-subdir' => self::REQ, 'config-dir' => self::REQ, 'existing-config' => false]): void
    {
        $additional = $recipeOrProfile;
        $recipeOrProfile = array_shift($additional) ?: '';
        $form_options = [];
        foreach ($additional as $arg) {
            list($key, $value) = explode('=', $arg, 2);

            // Allow for numeric and NULL values to be passed in.
            if (is_numeric($value)) {
                $value = (int) $value;
            } elseif ($value === 'NULL') {
                $value = null;
            }

            $form_options[$key] = $value;
        }

        $this->serverGlobals($this->bootstrapManager->getUri());
        list($recipe, $profile) = $this->determineRecipeOrProfile($recipeOrProfile, $options);
        $account_pass = $options['account-pass'] ?: StringUtils::generatePassword();

        // Was giving error during validate() so its here for now.
        if ($options['existing-config']) {
            $existing_config_dir = Settings::get('config_sync_directory');
            if ($existing_config_dir === null || !is_dir($existing_config_dir)) {
                throw new \Exception(dt('Existing config directory @dir not found', ['@dir' => $existing_config_dir]));
            }
            $this->logger()->info(dt('Installing from existing config at @dir', ['@dir' => $existing_config_dir]));
        }

        $settings = [
            'parameters' => [
                'profile' => $profile ?? '',
                'langcode' => $options['locale'],
                'existing_config' => $options['existing-config'],
            ],
            'forms' => [
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
            'config_install_path' => $options['config-dir'],
        ];

        if ($recipe) {
            if (version_compare(\Drupal::VERSION, '10.3.0') < 0) {
                throw new \Exception('Recipes are only supported on Drupal 10.3.0 and later.');
            }
            $settings['parameters']['recipe'] = $recipe;
        }

        $sql = SqlBase::create($options);
        if ($sql) {
            $db_spec = $sql->getDbSpec();
            $settings['forms']['install_settings_form'] = [
                'driver' => $db_spec['namespace'],
                $db_spec['namespace'] => $db_spec,
                'op' => dt('Save and continue'),
            ];
        }

        // Merge in the additional options.
        foreach ($form_options as $key => $value) {
            $current = &$settings['forms'];
            foreach (explode('.', $key) as $param) {
                $current = &$current[$param];
            }
            $current = $value;
        }

        $msg = 'Starting Drupal installation. This takes a while.';
        $this->logger()->notice(dt($msg));

        // Define some functions which alter away the install_finished task.
        require_once Path::join($this->config->get('drush.base-dir'), 'includes/site_install.inc');

        require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';
        // This can lead to an exit() in Drupal. See install_display_output() (e.g. config validation failure).
        // @todo Get Drupal to not call that function when on the CLI.
        try {
            drush_op('install_drupal', $this->autoloader, $settings, [$this, 'taskCallback']);
        } catch (AlreadyInstalledException $e) {
            if ($sql && !$this->programExists($sql->command())) {
                throw new \Exception(dt('Drush was unable to drop all tables because `@program` was not found, and therefore Drupal threw an AlreadyInstalledException. Ensure `@program` is available in your PATH.', ['@program' => $sql->command()]), $e->getCode(), $e);
            }
            throw $e;
        } catch (InstallerException $e) {
            throw new InstallerException(MailFormatHelper::htmlToText($e->getMessage()), $e->getTitle(), $e->getCode(), ($this->output()->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) ? $e : null);
        }

        if (empty($options['account-pass'])) {
            $this->logger()->success(dt('Installation complete.  User name: @name  User password: @pass', ['@name' => $options['account-name'], '@pass' => $account_pass]));
        } else {
            $this->logger()->success(dt('Installation complete.'));
        }
    }

    public function taskCallback($install_state): void
    {
        $this->logger()->notice('Performed install task: {task}', ['task' => $install_state['active_task']]);
    }

    /**
     * Determine if the passed parameter is a recipe directory, or a profile name.
     */
    protected function determineRecipeOrProfile($recipeOrProfile, $options): array
    {
        // Check for recipe relative to Drupal root
        if ($this->validateRecipe($recipeOrProfile)) {
            return [$recipeOrProfile, null];
        }

        // Check for recipe relative to cwd
        if (!empty($recipeOrProfile) && !Path::isAbsolute($recipeOrProfile)) {
            $relativeToCwdRecipePath = Path::join($this->getConfig()->cwd(), $recipeOrProfile);
            if ($this->validateRecipe($relativeToCwdRecipePath)) {
                return [$relativeToCwdRecipePath, null];
            }
        }

        // If $recipeOrProfile is not a recipe, we'll check to see if it is
        // a profile; however, first we will check and see if the parameter
        // matches the required naming conventions for a profile. If it does
        // not, we'll assume the user was trying to select a recipe that could
        // not be found.
        if (!empty($recipeOrProfile) && !$this->isValidProfileName($recipeOrProfile)) {
            throw new \Exception(dt('Could not find a recipe.yml file for @recipe', ['@recipe' => $recipeOrProfile]));
        }

        return [null, $this->determineProfile($recipeOrProfile, $options)];
    }

    /**
     * Determine whether the provided profile name meets naming conventions.
     *
     * We do not check for reserved names; if a profile name _might_ be
     * valid, we will pass it through to Drupal and let the system tell us
     * if it is not allowed.
     */
    protected function isValidProfileName(string $profile)
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $profile);
    }

    /**
     * Validates a user provided recipe.
     *
     * @param string $recipe
     *   The path to the recipe to validate.
     *
     * @return bool
     *   TRUE if the recipe exists, FALSE if not.
     */
    protected function validateRecipe(string $recipe): bool
    {
        // It is impossible to validate a recipe fully at this point because that
        // requires a container.
        if (!is_dir($recipe) || !is_file($recipe . '/recipe.yml')) {
            return false;
        }
        return true;
    }

    protected function determineProfile($profile, $options): string|bool
    {
        // Try to get profile from existing config if not provided as an argument.
        // @todo Arguably Drupal core [$boot->getKernel()->getInstallProfile()] could do this - https://github.com/drupal/drupal/blob/8.6.x/core/lib/Drupal/Core/DrupalKernel.php#L1606 reads from DB storage but not file storage.
        if (empty($profile) && $options['existing-config']) {
            FileCacheFactory::setConfiguration([FileCacheFactory::DISABLE_CACHE => true]);
            $config_directory = Settings::get('config_sync_directory');
            $source_storage = new FileStorage($config_directory);
            if (!$source_storage->exists('core.extension')) {
                throw new \Exception(dt('Existing configuration directory @config does not contain a core.extension.yml file.', ['@config' => $config_directory]));
            }
            $config = $source_storage->read('core.extension');
            $profile = $config['profile'] ?? false;
            return $profile;
        }

        if (empty($profile)) {
            $boot = $this->bootstrapManager->bootstrap();
            $kernel = $boot->getKernel();
            assert($kernel instanceof InstallerKernel);
            $profile = $kernel->getInstallProfile();
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
                install_begin_request($this->autoloader, $install_state);
                $profile = _install_select_profile($install_state);
            } catch (\Exception) {
                // This is only a best effort to provide a better default, no harm done
                // if it fails.
            }
        }

        // Drupal currently requires that non-interactive installs provide a profile.
        if (empty($profile)) {
            $profile = 'standard';
        }
        return $profile;
    }

    /**
     * Check to see if there are any .yml files in the provided config directory.
     */
    protected function hasConfigFiles($config): bool
    {
        $files = glob("$config/*.yml");
        return !empty($files);
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::INSTALL)]
    public function validate(CommandData $commandData): void
    {
        if ($sites_subdir = $commandData->input()->getOption('sites-subdir')) {
            $lower = strtolower($sites_subdir);
            if ($sites_subdir != $lower) {
                $this->logger()->warning(dt('Only lowercase sites-subdir are valid. Switching to !lower.', ['!lower' => $lower]));
                $commandData->input()->setOption('sites-subdir', $lower);
            }
            // Make sure that we will bootstrap to the 'sites-subdir' site.
            $this->bootstrapManager->setUri('http://' . $sites_subdir);
        }

        if ($config = $commandData->input()->getOption('config-dir')) {
            $this->validateConfigDir($commandData, $config);
        }

        try {
            // Try to get any already configured database information.
            $this->bootstrapManager->bootstrapMax(DrupalBootLevels::CONFIGURATION, $commandData->annotationData());

            // See https://github.com/drush-ops/drush/issues/3903.
            // We may have bootstrapped with /default/settings.php instead of the sites-subdir one.
            if ($sites_subdir && "sites/$sites_subdir" !== $this->bootstrapManager->bootstrap()->confpath()) {
                Database::removeConnection('default');
            }

            // Try to create an sql accessor object. If we cannot, then we will
            // presume that we have no database credential information, and we
            // will prompt the user to provide them in the 'catch' block below.
            SqlBase::create($commandData->input()->getOptions());
        } catch (\Exception) {
            // Prompt for the db-url data if it was not provided via --db-url.
            // TODO: we should only 'ask' in hook interact, never in hook validate
            if ($commandData->input()->getOption('db-url') == '') {
                global $install_state;
                try {
                    // Do some install booting to get basic services available.
                    $recipeOrProfile = array_shift($commandData->input()->getArgument('recipeOrProfile')) ?: '';
                    list($recipe, $profile) = $this->determineRecipeOrProfile($recipeOrProfile, $commandData->input()->getOptions());
                    require_once DRUSH_DRUPAL_CORE . '/includes/install.core.inc';
                    $install_state = ['interactive' => false] + install_state_defaults();
                    $install_state['parameters']['profile'] = $profile ?? '';
                    if ($recipe) {
                        $install_state['parameters']['recipe'] = $recipe;
                    }
                    install_begin_request($this->autoloader, $install_state);

                    // Get the installable drivers.
                    $driverList = Database::getDriverList()->getInstallableList();
                    $driverSelectOptions = [];
                    foreach ($driverList as $namespace => $driverExtension) {
                        $driverSelectOptions[$namespace] = $driverExtension->getInstallTasks()->name();
                    }

                    // Ask questions to get our data.
                    $driverNamespace = $this->io()->select('Select the database driver', $driverSelectOptions);
                    $formOptions = $driverList[$driverNamespace]->getInstallTasks()->getFormOptions([]);
                    $databaseInfo = [
                        'driver' => $driverList[$driverNamespace]->getDriverName(),
                        'module' => $driverList[$driverNamespace]->getModule()->getName(),
                    ];
                    $databaseInfo['database'] = $this->io()->ask(
                        $formOptions['database']['#title'],
                        default: $formOptions['database']['#default_value'] ?: 'drupal',
                        hint: (string) ($formOptions['database']['#description'] ?? null),
                    );
                    if (isset($formOptions['username'])) {
                        $databaseInfo['username'] = $this->io()->ask(
                            $formOptions['username']['#title'],
                            default: 'drupal',
                            hint: (string) ($formOptions['username']['#description'] ?? null),
                        );
                    }
                    if (isset($formOptions['password'])) {
                        $databaseInfo['password'] = $this->io()->password(
                            $formOptions['password']['#title'],
                            hint: (string) ($formOptions['password']['#description'] ?? null),
                        );
                    }
                    if (isset($formOptions['advanced_options']['host'])) {
                        $databaseInfo['host'] = $this->io()->ask(
                            $formOptions['advanced_options']['host']['#title'],
                            default: $formOptions['advanced_options']['host']['#default_value'],
                            hint: (string) ($formOptions['advanced_options']['host']['#description'] ?? null),
                        );
                    }
                    if (isset($formOptions['advanced_options']['port'])) {
                        $databaseInfo['port'] = $this->io()->ask(
                            $formOptions['advanced_options']['port']['#title'],
                            default: $formOptions['advanced_options']['port']['#default_value'],
                            hint: (string) ($formOptions['advanced_options']['port']['#description'] ?? null),
                        );
                    }
                    if (isset($formOptions['advanced_options']['prefix'])) {
                        $databaseInfo['prefix'] = $this->io()->ask(
                            $formOptions['advanced_options']['prefix']['#title'],
                            default: $formOptions['advanced_options']['prefix']['#default_value'],
                            hint: MailFormatHelper::htmlToText($formOptions['advanced_options']['prefix']['#description'] ?? null),
                        );
                    }
                    $connectionClass = $driverNamespace . '\\Connection';
                    $db_url = $connectionClass::createUrlFromConnectionOptions($databaseInfo);
                    $commandData->input()->setOption('db-url', $db_url);
                } finally {
                    unset($install_state);
                }

                try {
                    // Try to instantiate an sql accessor object from the
                    // provided credential values.
                    SqlBase::create($commandData->input()->getOptions());
                } catch (\Exception $e) {
                    throw new \Exception(dt('Could not determine database connection parameters. Pass --db-url option.'), $e->getCode(), $e);
                }
            }
        }
    }

    /**
     * Perform setup tasks before installation.
     */
    #[CLI\Hook(type: HookManager::PRE_COMMAND_HOOK, target: self::INSTALL)]
    public function pre(CommandData $commandData): void
    {
        $db_spec = [];
        if ($sql = SqlBase::create($commandData->input()->getOptions())) {
            $db_spec = $sql->getDbSpec();
        }

        // This command is 'bootstrap root', so we should always have a
        // Drupal root. If we do not, $aliasRecord->root will throw.
        $aliasRecord = $this->siteAliasManager->getSelf();
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
        $sitesfile_write = realpath($confPath) !== $default && !file_exists($sitesfile);

        $msg = [];
        if (!file_exists($settingsfile)) {
            $msg[] = dt('Create a @settingsfile file', ['@settingsfile' => $settingsfile]);
        }
        if ($sitesfile_write) {
            $msg[] = dt('Create a @sitesfile file', ['@sitesfile' => $sitesfile]);
        }

        $program = $sql ? $sql->command() : 'UNKNOWN';
        $program_exists = $this->programExists($program);
        if (!$program_exists) {
            $this->logger()->warning(dt('Program @program not found. Proceed if you have already created or emptied the Drupal database.', ['@program' => $program]));
        } elseif ($sql->dbExists()) {
            $msg[] = dt("DROP all tables in your '@db' database.", ['@db' => $db_spec['database']]);
        } else {
            $msg[] = dt("CREATE the '@db' database.", ['@db' => $db_spec['database']]);
        }

        if ($msg) {
            $this->io()->text(dt('You are about to:'));
            $this->io()->listing($msg);
        }

        if (!$this->io()->confirm(dt('Do you want to continue?'))) {
            throw new UserAbortException();
        }

        // Can't install without sites subdirectory and settings.php.
        if (!file_exists($confPath)) {
            if (!$this->getConfig()->simulate()) {
                $fs = new Filesystem();
                $fs->mkdir($confPath);
            }
        } else {
            $this->logger()->info(dt('Sites directory @subdir already exists - proceeding.', ['@subdir' => $confPath]));
        }

        if (!drush_file_not_empty($settingsfile)) {
            if (!drush_op('copy', 'sites/default/default.settings.php', $settingsfile) && !$this->getConfig()->simulate()) {
                throw new \Exception(dt('Failed to copy sites/default/default.settings.php to @settingsfile', ['@settingsfile' => $settingsfile]));
            }
        }

        // Write an empty sites.php if we using multi-site.
        if ($sitesfile_write) {
            if (!drush_op('copy', 'sites/example.sites.php', $sitesfile) && !$this->getConfig()->simulate()) {
                throw new \Exception(dt('Failed to copy sites/example.sites.php to @sitesfile', ['@sitesfile' => $sitesfile]));
            }
        }

        // We need to be at least at DRUSH_BOOTSTRAP_DRUPAL_SITE to select the site uri to install to
        define('MAINTENANCE_MODE', 'install');
        $this->bootstrapManager->doBootstrap(DrupalBootLevels::SITE);

        if ($program_exists && !$sql->dropOrCreate()) {
            $this->logger()->warning(dt('Failed to drop or create the database. Do it yourself before installing. @error', ['@error' => $sql->getProcess()->getErrorOutput()]));
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
            $sites = [];
            include $sites_file;
            // @phpstan-ignore booleanAnd.alwaysFalse, notIdentical.alwaysFalse
            if ($sites !== [] && array_key_exists($uri, $sites)) {
                return $sites[$uri];
            }
        }
        // Fall back to default directory if it exists.
        if (file_exists(Path::join($root, 'sites', 'default'))) {
            return 'default';
        }
        return false;
    }

    /**
     * Fake the necessary HTTP headers that the Drupal installer still needs:
     * @see https://github.com/drupal/drupal/blob/d260101f1ea8a6970df88d2f1899248985c499fc/core/includes/install.core.inc#L287
     */
    public function serverGlobals($drupal_base_url): void
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

    /**
     * Assure that a config directory exists and is populated.
     *
     * @param $directory
     * @throws \Exception
     */
    protected function validateConfigDir(CommandData $commandData, $directory): void
    {
        if (!file_exists($directory)) {
            throw new \Exception(dt('The config source directory @config does not exist.', ['@config' => $directory]));
        }
        if (!is_dir($directory)) {
            throw new \Exception(dt('The config source @config is not a directory.', ['@config' => $directory]));
        }
        // Skip config import with a warning if specified config dir is empty.
        if (!$this->hasConfigFiles($directory)) {
            $this->logger()->warning(dt('Configuration import directory @config does not contain any configuration; will skip import.', ['@config' => $directory]));
            $commandData->input()->setOption('config-dir', '');
        }
    }
}
