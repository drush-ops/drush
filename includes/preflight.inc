<?php

/**
 * @file
 * Preflight, postflight and shutdown code.
 */

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Cache\CommandCache;
use Drush\Drush;
use Drush\Log\LogLevel;
use League\Container\Container;
use Robo\Contract\BuilderAwareInterface;
use Robo\Robo;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\PathUtil\Path;

/**
 * The main Drush function.
 *
 * - Parses the command line arguments, configuration files and environment.
 * - Prepares and executes a Drupal bootstrap, if possible,
 * - Dispatches the given command.
 *
 * function_exists('drush_main') may be used by modules to detect whether
 * they are being called from Drush.  See http://drupal.org/node/1181308
 * and http://drupal.org/node/827478
 *
 * @return mixed
 *   Whatever the given command returns.
 */
function drush_main() {
  // Load Drush core include files, create the container and
  // parse commandline arguments
  $container = drush_preflight_prepare();
  if ($container === FALSE) {
    return(1);
  }
  // Start code coverage collection.
  if ($coverage_file = drush_get_option('drush-coverage', FALSE)) {
    drush_set_context('DRUSH_CODE_COVERAGE', $coverage_file);
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    register_shutdown_function('drush_coverage_shutdown');
  }

  // Load the global Drush configuration files, and global Drush commands.
  // Find the selected site based on --root, --uri or cwd
  // Preflight the selected site, and load any configuration and commandfiles associated with it.
  // Select and return the bootstrap class.
  $bootstrap = drush_preflight();

  $return = '';
  if (!drush_get_error()) {
    // TODO: If 'DRUSH_SYMFONY' is set, then disable the legacy command
    // runner, and use only the Symfony Application.  n.b. the legacy runner
    // will also try to run commands via the Symfony Application runner if
    // it cannot find a matching legacy Drush command.
    if (getenv('DRUSH_SYMFONY')) {
      drush_log(dt("Dispatching directly with Symfony application (DRUSH_SYMFONY set)"), LogLevel::BOOTSTRAP);

      // Get the application and run it.
      // TODO: We need a new way to handle @alias arguments,
      // remote command dispatching, bootstrapping, etc.
      $application = $container->get('application');
      $input = drush_symfony_input();
      $application->run($input);
    }
    else {
      // Do any necessary preprocessing operations on the command,
      // perhaps handling immediately.
      $command_handled = drush_preflight_command_dispatch();
      if (!$command_handled) {
        drush_log(dt("Dispatching using Drush bootstrapAndDispatch"), LogLevel::BOOTSTRAP);
        $return = $bootstrap->bootstrapAndDispatch();
      }
    }
  }

  // TODO: Get rid of global variable access here, and just trust
  // the bootstrap object returned from drush_preflight().  This will
  // require some adjustments to Drush bootstrapping.
  // See: https://github.com/drush-ops/drush/pull/1303
  if ($bootstrap = Drush::bootstrap()) {
    $bootstrap->terminate();
  }
  drush_postflight();
  if (is_object($return)) {
    $return = 0;
  }

  // How strict are we?  If we are very strict, turn 'ok' into 'error'
  // if there are any warnings in the log.
  if (($return == 0) && (drush_get_option('strict') > 1) && drush_log_has_errors()) {
    $return = 1;
  }

  // After this point the drush_shutdown function will run,
  // exiting with the correct exit code.
  return $return;
}

function drush_symfony_input() {
  // Symfony ArgvInput is touchy; fix up the args.
  $argv = $_SERVER['argv'];
  $scriptname = array_shift($argv);
  // Get rid of --php= and --php-options=
  while (strpos($argv[0], '--php') === 0) {
    array_shift($argv);
  }
  array_unshift($argv, $scriptname);

  return new ArgvInput($argv);
}

/**
 * Prepare Drush for preflight.
 *
 * Runs before drush_main().
 *
 * @see drush_main()
 * @see drush.php
 */
function drush_preflight_prepare() {
  define('DRUSH_BASE_PATH', dirname(dirname(__FILE__)));

  // Local means that autoload.php is inside of Drush. That is, Drush is its own Composer project.
  // Global means autoload.php is outside of Drush. That is, Drush is a dependency of a bigger project.
  $local_vendor_path = DRUSH_BASE_PATH . '/vendor/autoload.php';
  $global_vendor_path = DRUSH_BASE_PATH . '/../../../vendor/autoload.php';

  // Check for Unish's autoload.php, a local autoload.php, or a global autoload.php.
  if (file_exists(getenv('DRUSH_AUTOLOAD_PHP'))) {
    $vendor_path = getenv('DRUSH_AUTOLOAD_PHP');
  }
  elseif (file_exists($local_vendor_path)) {
    $vendor_path = $local_vendor_path;
  }
  elseif (file_exists($global_vendor_path)) {
    $vendor_path = $global_vendor_path;
  }
  else {
    $msg = "Unable to load autoload.php. Run composer install to fetch dependencies and write autoload.php (http://docs.drush.org/en/master/install/).\n";
    fwrite(STDERR, $msg);
    return FALSE;
  }
  $classloader = require $vendor_path;

  require_once DRUSH_BASE_PATH . '/includes/bootstrap.inc';
  require_once DRUSH_BASE_PATH . '/includes/environment.inc';
  require_once DRUSH_BASE_PATH . '/includes/annotationcommand_adapter.inc';
  require_once DRUSH_BASE_PATH . '/includes/command.inc';
  require_once DRUSH_BASE_PATH . '/includes/drush.inc';
  require_once DRUSH_BASE_PATH . '/includes/backend.inc';
  require_once DRUSH_BASE_PATH . '/includes/batch.inc';
  require_once DRUSH_BASE_PATH . '/includes/context.inc';
  require_once DRUSH_BASE_PATH . '/includes/sitealias.inc';
  require_once DRUSH_BASE_PATH . '/includes/exec.inc';
  require_once DRUSH_BASE_PATH . '/includes/drupal.inc';
  require_once DRUSH_BASE_PATH . '/includes/output.inc';
  require_once DRUSH_BASE_PATH . '/includes/cache.inc';
  require_once DRUSH_BASE_PATH . '/includes/filesystem.inc';
  require_once DRUSH_BASE_PATH . '/includes/legacy.inc';

  /*
   * @deprecated. Use Drush::getVersion().
   */
  define('DRUSH_VERSION', Drush::getVersion());

  /*
   * @deprecated. Use Drush::getMajorVersion().
   */
  define('DRUSH_MAJOR_VERSION', Drush::getMajorVersion());

  /*
   * @deprecated. Use Drush::getMinorVersion().
   */
  define('DRUSH_MINOR_VERSION', Drush::getMinorVersion());

  // We need to load our services right away, as we cannot log
  // or do much else until after this is done.
  $container = drush_init_dependency_injection_container();

  drush_init_application_global_options();

  // Terminate immediately unless invoked as a command line script
  if (!drush_verify_cli()) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Drush is designed to run via the command line.'));
  }

  // Check supported version of PHP.
  define('DRUSH_MINIMUM_PHP', '5.6.0');
  if (version_compare(phpversion(), DRUSH_MINIMUM_PHP) < 0 && !getenv('DRUSH_NO_MIN_PHP')) {
    return drush_set_error('DRUSH_REQUIREMENTS_ERROR', dt('Your command line PHP installation is too old. Drush requires at least PHP !version. To suppress this check, set the environment variable DRUSH_NO_MIN_PHP=1', array('!version' => DRUSH_MINIMUM_PHP)));
  }

  if (!$return = _drush_environment_check_php_ini()) {
    return; // An error was logged.
  }

  define('DRUSH_REQUEST_TIME', microtime(TRUE));

  drush_set_context('argc', $GLOBALS['argc']);
  drush_set_context('argv', $GLOBALS['argv']);

  // Set an error handler and a shutdown function
  set_error_handler('drush_error_handler');
  register_shutdown_function('drush_shutdown');

  // TODO: Remove drush_parse_args
  // We need some global options/arguments processed at this early stage.
  drush_parse_args();

  // Process initial global options such as --debug.
  _drush_preflight_global_options();

  drush_log(dt("drush_preflight_prepare() loaded autoloader at !autoloader", array('!autoloader' => Path::canonicalize($vendor_path))), LogLevel::BOOTSTRAP);
  // Stash our vendor path and classloader.
  drush_set_context('DRUSH_VENDOR_PATH', dirname($vendor_path));
  drush_set_context('DRUSH_CLASSLOADER', $classloader);


  return $container;
}

/**
 * Set up our dependency injection container.
 *
 * The Drupal6 boot service is needed in order to show the D6 deprecation message.
 */
function drush_init_dependency_injection_container($input = null, $output = null) {
  // Create default input and output objects if they were not provided
  if (!$input) {
      $input = new StringInput('');
  }
  if (!$output) {
      $output = new \Symfony\Component\Console\Output\ConsoleOutput();
  }
  // Set up our dependency injection container.
  $container = new \League\Container\Container();
  $roboConfig = new \Robo\Config(); // TODO: make a global Drush config class derived from \Robo\Config. Then use $drushConfig here instead of $roboConfig

  $application = new \Symfony\Component\Console\Application('Drush Commandline Tool', Drush::getVersion());

  \Robo\Robo::configureContainer($container, $application, $roboConfig, $input, $output);
  $container->add('container', $container);

  // Override Robo's logger with our own
  $container->share('logger', 'Drush\Log\Logger')
    ->withArgument('output')
    ->withMethodCall('setLogOutputStyler', ['logStyler']);

  // Override Robo's formatter manager with our own
  // @todo not sure that we'll use this. Maybe remove it.
  $container->share('formatterManager', \Drush\Formatters\DrushFormatterManager::class)
      ->withMethodCall('addDefaultFormatters', [])
      ->withMethodCall('addDefaultSimplifiers', []);

  // Add some of our own objects to the container
  $container->share('bootstrap.default', 'Drush\Boot\EmptyBoot');
  $container->share('bootstrap.drupal6', 'Drush\Boot\DrupalBoot6');
  $container->share('bootstrap.drupal7', 'Drush\Boot\DrupalBoot7');
  $container->share('bootstrap.drupal8', 'Drush\Boot\DrupalBoot8');
  $container->share('bootstrap.manager', 'Drush\Boot\BootstrapManager')
    ->withArgument('bootstrap.default');
  $container->extend('bootstrap.manager')
    ->withMethodCall('add', ['bootstrap.drupal6'])
    ->withMethodCall('add', ['bootstrap.drupal7'])
    ->withMethodCall('add', ['bootstrap.drupal8']);

  // Robo does not manage the command discovery object in the container,
  // but we will register and configure one for our use.
  $container->share('commandDiscovery', 'Consolidation\AnnotatedCommand\CommandFileDiscovery')
    ->withMethodCall('addSearchLocation', ['CommandFiles'])
    ->withMethodCall('setSearchPattern', ['#.*(Commands|CommandFile).php$#']);

  // Store the container in the \Drush object
  Drush::setContainer($container);
  \Robo\Robo::setContainer($container);



  // Add our own callback to the hook manager
  $hookManager = $container->get('hookManager');
  $hookManager->addOutputExtractor(new \Drush\Backend\BackendResultSetter());
  // @todo: do we need both backend result setters? The one below should be removed at some point.
  $hookManager->add('annotatedcomand_adapter_backend_result', HookManager::EXTRACT_OUTPUT);

  // Install our command cache into the command factory
  $cacheBackend = _drush_cache_get_object('factory');
  $commandCacheDataStore = new CommandCache($cacheBackend);

  $factory = $container->get('commandFactory');
  $factory->setIncludeAllPublicMethods(false);
  $factory->setDataStore($commandCacheDataStore);

  // It is necessary to set the dispatcher when using configureContainer
  $eventDispatcher = $container->get('eventDispatcher');
  $eventDispatcher->addSubscriber(new \Drush\Command\GlobalOptionsEventListener());
  $application->setDispatcher($eventDispatcher);

  return $container;
}

// TODO: Where should this go?
function drush_init_application_global_options() {
  $application = Drush::getApplication();
  $definition = $application->getDefinition();

  // TODO: We should make a better way to manage global options
  $globalOptions = drush_get_global_options();

  foreach ($globalOptions as $option => $info) {
    $info += [
      'short-form' => null,
    ];
    // TODO: We can't register options that Symfony has already registered,
    // but we need a better way than this.
    if (array_key_exists('symfony-conflict', $info)) {
      continue;
    }
    if ($info['short-form'] == 'n') {
      $info['short-form'] = null;
    }
    $description = $info['description'];
    $default = null;
    $mode = InputOption::VALUE_NONE;
    if (array_key_exists('example-value', $info)) {
      $mode = InputOption::VALUE_REQUIRED;
      // TODO: at the moment, global options do not know their default values,
      // but if they did, it would look like this:
      if (array_key_exists('default-value', $info)) {
        $default = $info['default-value'];
        $mode = InputOption::VALUE_OPTIONAL;
      }
    }
    // TODO: Any need to make use of InputOption::VALUE_IS_ARRAY?

    $definition->addOption(
        new InputOption("--$option", $info['short-form'], $mode, $description, $default)
    );

    // TODO: If the default is TRUE, add a --no alias option.
    if ($default === TRUE) {
      $definition->addOption(
          new InputOption("--no-$option", null, $mode, "Negation of --$option.", FALSE)
      );
    }
  }
}

function drush_init_register_command_files($container, $commandFiles) {
  foreach ($commandFiles as $sourcePath => $className) {
    if (!class_exists($className)) {
      include $sourcePath;
    }
    $classAlias = str_replace('\\', '', $className);
    // Add and fetch our class from the container to apply the inductors
    $container->share($classAlias, $className);
    $commandFileInstance = $container->get($classAlias);
    if ($commandFileInstance instanceof BuilderAwareInterface) {
        $builder = $container->get('collectionBuilder', [$commandFileInstance]);
        $commandFileInstance->setBuilder($builder);
    }
    drush_add_command_instance($container, $commandFileInstance);
  }
}

function drush_add_command_instance($container, $commandInstance, $includeAllPublicMethods = true) {
  if ($commandInstance instanceof Symfony\Component\Console\Command\Command) {
    drush_add_command_to_application($container, $commandInstance);
    return;
  }
  drush_create_commands_from_command_instance($container, $commandInstance);
}

function drush_create_commands_from_command_instance($container, $commandInstance, $includeAllPublicMethods = true) {
  $application = $container->get('application');
  $commandFactory = $container->get('commandFactory');
  $commandList = $commandFactory->createCommandsFromClass($commandInstance, $includeAllPublicMethods);
  foreach ($commandList as $command) {
    drush_add_command_to_application($container, $command);
  }
}

function drush_add_command_to_application($container, $command) {
  $application = $container->get('application');
  $commandName = $command->getName();
  $drushAlias = strtr($commandName, ':', '-');
  if ($commandName != $drushAlias) {
    $aliases = $command->getAliases();
    $command->setAliases(array_unique(array_merge($aliases, [$drushAlias])));
  }
  $application->add($command);
}

/**
 * During the initialization of Drush, this is the first
 * step where we load our configuration and commandfiles,
 * and select the site we are going to operate on; however,
 * we take no irreversible actions (e.g. site bootstrapping).
 * This allows commands that are declared with no bootstrap
 * to select a new site root and bootstrap it.
 *
 * In this step we will register the shutdown function,
 * parse the command line arguments and store them in their
 * related contexts.
 *
 * Configuration files (drushrc.php) that are
 *   a) Specified on the command line
 *   b) Stored in the root directory of drush.php
 *   c) Stored in the home directory of the system user.
 *
 * Additionally the DRUSH_QUIET and DRUSH_BACKEND contexts,
 * will be evaluated now, as they need to be set very early in
 * the execution flow to be able to take affect.
 *
 * @return \Drush\Boot\Boot;
 */
function drush_preflight() {
  // Create an alias '@none' to represent no Drupal site
  _drush_sitealias_cache_alias('@none', array('root' => '', 'uri' => ''));

  // Discover terminal width for pretty output.
  _drush_preflight_columns();

  // Display is tidy now that column width has been handled.
  drush_log(dt('Starting Drush preflight.'), LogLevel::BOOTSTRAP);

  // Statically define a way to call drush again.
  define('DRUSH_COMMAND', drush_find_drush());

  // prime the CWD cache
  drush_cwd();

  // Set up base environment for system-wide file locations.
  _drush_preflight_base_environment();

  // Setup global alias_paths[] in context system.
  if (!drush_get_option('local')) {
    _drush_preflight_alias_path();
  }
  if (!drush_get_option('local')) {

    // Load a drushrc.php file in the $ETC_PREFIX/etc/drush directory.
    drush_load_config('system');

    // Load a drushrc.php file at ~/.drushrc.php.
    drush_load_config('user');

    // Load a drushrc.php file in the ~/.drush directory.
    drush_load_config('home.drush');
  }

  // Load a custom config specified with the --config option.
  drush_load_config('custom');

  _drush_preflight_global_options();
  // Load all the commandfiles findable from any of the
  // scopes listed above.
  _drush_find_commandfiles_drush();

  // Look up the alias identifier that the user wants to use,
  // either via an argument or via 'site-set'.
  $target_alias = drush_sitealias_check_arg_and_site_set();

  // Process the site alias that specifies which instance
  // of Drush (local or remote) this command will operate on.
  // We must do this after we load our config files (so that
  // site aliases are available), but before the rest of
  // Drush preflight and Drupal root bootstrap phase are
  // done, since site aliases may set option values that
  // affect these phases.
  $alias_record = _drush_sitealias_set_context_by_name($target_alias);

  // Find the selected site based on --root, --uri or cwd
  drush_preflight_root();

  // Preflight the selected site, and load any configuration and commandfiles associated with it.
  drush_preflight_site();

  // Check to see if anything changed during the 'site' preflight
  // that might allow us to find our alias record now.
  if (empty($alias_record)) {
    $alias_record = _drush_sitealias_set_context_by_name($target_alias);
  }

  // Fail if we could not find the selected site alias.
  if ($target_alias && empty($alias_record)) {
    // We will automatically un-set the site-set alias if it could not be found.
    // Otherwise, we'd be stuck -- the user would only be able to execute Drush
    // commands again after `drush @none site-set @none`, and most folks would
    // have a hard time figuring that out.
    $site_env = drush_sitealias_site_get();
    if ($site_env == $target_alias) {
      drush_sitealias_site_clear();
    }
    return drush_set_error('DRUSH_BOOTSTRAP_NO_ALIAS', dt("Could not find the alias !alias", array('!alias' => $target_alias)));
  }

  // If applicable swaps in shell alias values.
  drush_shell_alias_replace($target_alias);

  // Copy global options to their respective contexts
  _drush_preflight_global_options();

  // Set environment variables based on #env-vars.
  drush_set_environment_vars($alias_record);

  // Select the bootstrap object and return it.
  return Drush::bootstrapManager()->bootstrap();
}

/**
 * If --root is provided, set context.
 */
function drush_preflight_root() {
  $root = drush_get_option('root');
  Drush::bootstrapManager()->locateRoot($root);
  $root = Drush::bootstrapManager()->getRoot();

  // Load the autoload file and provide it to the bootstrap manager.
  $siteAutoloader = drush_drupal_load_autoloader($root);
  Drush::bootstrapManager()->setAutoloader($siteAutoloader);

  // Load the config options from Drupal's /drush, ../drush, and sites/all/drush directories,
  // even prior to bootstrapping the root.
  drush_load_config('drupal');
}

function drush_preflight_site() {
  // Load the Drupal site configuration options upfront.
  drush_load_config('site');

  // Determine URI and set constants/contexts accordingly. Keep this after loading of drupal,site configs.
  _drush_preflight_uri();

  // If someone set 'uri' in the 'site' context, then copy it
  // to the 'process' context (to give it a higher priority
  // than the 'cli' and 'alias' contexts) and reset our selected
  // site and @self alias.
  $uri = drush_get_option('uri');
  if ($uri != drush_get_option('uri', $uri, 'site')) {
    drush_set_option('uri', drush_get_option('uri', $uri, 'site'));
    _drush_preflight_uri();
  }

  // Create a @self site alias record.
  drush_sitealias_create_self_alias();
}

function _drush_preflight_global_options() {
  // Debug implies verbose
  $verbose = drush_get_option('verbose', FALSE);
  $debug = drush_get_option('debug', FALSE);
  drush_set_context('DRUSH_VERBOSE',      $verbose || $debug);
  drush_set_context('DRUSH_DEBUG',        $debug);
  drush_set_context('DRUSH_DEBUG_NOTIFY', $verbose && $debug);
  drush_set_context('DRUSH_SIMULATE',     drush_get_option('simulate', FALSE));

  // Backend implies affirmative unless negative is explicitly specified
  drush_set_context('DRUSH_NEGATIVE',    drush_get_option('no', FALSE));
  drush_set_context('DRUSH_AFFIRMATIVE', drush_get_option(array('yes', 'pipe'), FALSE) || (drush_get_context('DRUSH_BACKEND') && !drush_get_context('DRUSH_NEGATIVE')));

  // Pipe implies quiet.
  drush_set_context('DRUSH_QUIET', drush_get_option(array('quiet', 'pipe')));
  drush_set_context('DRUSH_PIPE', drush_get_option('pipe'));

  // Suppress colored logging if --nocolor option is explicitly given or if
  // terminal does not support it.
  $nocolor = (drush_get_option('nocolor', FALSE));
  if (!$nocolor) {
    // Check for colorless terminal.  If there is no terminal, then
    // 'tput colors 2>&1' will return "tput: No value for $TERM and no -T specified",
    // which is not numeric and therefore will put us in no-color mode.
    $colors = exec('tput colors 2>&1');
    $nocolor = !($colors === FALSE || (is_numeric($colors) && $colors >= 3));
  }
  drush_set_context('DRUSH_NOCOLOR', $nocolor);

  // Copy the simulated option to the Robo configuration object.
  // TODO: Long-term there should be a better way to do this (e.g. via an event listener)
  $config = Drush::service('config');
  $config->set(\Robo\Config\Config::SIMULATE, drush_get_context('DRUSH_SIMULATE'));

  // Copy the output verbosity into the output object
  $verbosity = OutputInterface::VERBOSITY_NORMAL;
  if (drush_get_context('DRUSH_VERBOSE')) {
    $verbosity = OutputInterface::VERBOSITY_VERBOSE;
  }
  if (drush_get_context('DRUSH_DEBUG')) {
    $verbosity = OutputInterface::VERBOSITY_DEBUG;
  }
  $output = Drush::service('output');
  $output->setVerbosity($verbosity);
}

/**
 * Sets up basic environment that controls where Drush looks for files on a
 * system-wide basis. Important to call for "early" functions that need to
 * work with unit tests.
 */
function _drush_preflight_base_environment() {
  // Copy ETC_PREFIX and SHARE_PREFIX from environment variables if available.
  // This alters where we check for server-wide config and alias files.
  // Used by unit test suite to provide a clean environment.
  if (getenv('ETC_PREFIX')) drush_set_context('ETC_PREFIX', getenv('ETC_PREFIX'));
  if (getenv('SHARE_PREFIX')) drush_set_context('SHARE_PREFIX', getenv('SHARE_PREFIX'));

  drush_set_context('DOC_PREFIX', DRUSH_BASE_PATH);
  if (!file_exists(DRUSH_BASE_PATH . '/README.md') && file_exists(drush_get_context('SHARE_PREFIX', '/usr') . '/share/doc/drush' . '/README.md')) {
    drush_set_context('DOC_PREFIX', drush_get_context('SHARE_PREFIX', '/usr') . '/share/doc/drush');
  }

  $default_prefix_configuration = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '';
  $default_prefix_commandfile = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '/usr';
  $site_wide_configuration_dir = drush_get_context('ETC_PREFIX', $default_prefix_configuration) . '/etc/drush';
  $site_wide_commandfile_dir = drush_get_context('SHARE_PREFIX', $default_prefix_commandfile) . '/share/drush/commands';
  drush_set_context('DRUSH_SITE_WIDE_CONFIGURATION', $site_wide_configuration_dir);
  drush_set_context('DRUSH_SITE_WIDE_COMMANDFILES', $site_wide_commandfile_dir);

  $server_home = drush_server_home();
  if (isset($server_home)) {
    drush_set_context('DRUSH_PER_USER_CONFIGURATION', $server_home . '/.drush');
  }
}

/*
 * Set the terminal width, used for wrapping table output.
 * Normally this is exported using tput in the drush script.
 * If this is not present we do an additional check using stty here.
 * On Windows in CMD and PowerShell is this exported using mode con.
 */
function _drush_preflight_columns() {
  if (!($columns = getenv('COLUMNS'))) {
    // Trying to export the columns using stty.
    exec('stty size 2>&1', $columns_output, $columns_status);
    if (!$columns_status) $columns = preg_replace('/\d+\s(\d+)/', '$1', $columns_output[0], -1, $columns_count);

    // If stty fails and Drush us running on Windows are we trying with mode con.
    if (($columns_status || !$columns_count) && drush_is_windows()) {
      $columns_output = array();
      exec('mode con', $columns_output, $columns_status);
      if (!$columns_status && is_array($columns_output)) {
        $columns = (int)preg_replace('/\D/', '', $columns_output[4], -1, $columns_count);
      }
      else {
        drush_log(dt('Drush could not detect the console window width. Set a Windows Environment Variable of COLUMNS to the desired width.'), LogLevel::WARNING);
      }
    }

    // Failling back to default columns value
    if (empty($columns)) {
      $columns = 80;
    }
  }
  // If a caller wants to reserve some room to add additional
  // information to the drush output via post-processing, the
  // --reserve-margin flag can be used to declare how much
  // space to leave out.  This only affects drush functions
  // such as drush_print_table() that wrap the output.
  $columns -= drush_get_option('reserve-margin', 0);
  drush_set_context('DRUSH_COLUMNS', $columns);
}

function _drush_preflight_alias_path() {
  $alias_path =& drush_get_context('ALIAS_PATH');
  $default_prefix_configuration = drush_is_windows() ? getenv('ALLUSERSPROFILE') . '/Drush' : '';
  $site_wide_configuration_dir = drush_get_context('ETC_PREFIX', $default_prefix_configuration) . '/etc/drush';
  $alias_path[] = drush_sitealias_alias_base_directory($site_wide_configuration_dir);

  $server_home = drush_server_home();
  if (isset($server_home)) {
    $alias_path[] = drush_sitealias_alias_base_directory($server_home . '/.drush');
  }
}

/*
 * Set root and uri.
 */
function _drush_preflight_root_uri() {
  drush_preflight_root();
  _drush_preflight_uri();
}

/**
 * If --uri is provided, set context.
 */
function _drush_preflight_uri() {
  $uri = drush_get_option('uri', '');
  drush_set_context('DRUSH_SELECTED_URI', $uri);
}

function _drush_find_commandfiles_drush() {
  $searchpath = [];

  // User commands, specified by 'include' option
  $include = drush_get_context('DRUSH_INCLUDE', array());
  foreach ($include as $path) {
    if (is_dir($path)) {
      drush_log('Include ' . $path, LogLevel::INFO);
      $searchpath[] = $path;
    }
  }

  if (!drush_get_option('local')) {
    // System commands, residing in $SHARE_PREFIX/share/drush/commands
    $share_path = drush_get_context('DRUSH_SITE_WIDE_COMMANDFILES');
    if (is_dir($share_path)) {
      $searchpath[] = $share_path;
    }

    // User commands, residing in ~/.drush
    $per_user_config_dir = drush_get_context('DRUSH_PER_USER_CONFIGURATION');
    if (!empty($per_user_config_dir)) {
      $searchpath[] = $per_user_config_dir;
    }
  }

  // @todo the zero parameter is a bit weird here. It's $phase.
  _drush_add_commandfiles($searchpath, 0);

  // Also discover Drush's own annotation commands.
  $discovery = annotationcommand_adapter_get_discovery();
  $annotation_commandfiles = $discovery->discover(DRUSH_BASE_PATH . '/src', '\Drush');

  // And, finally, search for Annotated commandfiles in the $searchpath.
  $searchpath = array_map(
    function ($item) {
      if (strtolower(basename($item)) == 'commands') {
        return dirname($item);
      }
      return $item;
    },
    $searchpath
  );
  $global_drush_extensions = $discovery->discover($searchpath, '\Drush');
  $annotation_commandfiles += $global_drush_extensions;

  drush_set_context('DRUSH_ANNOTATED_COMMANDFILES', $annotation_commandfiles);
}

/**
 * Handle any command preprocessing that may need to be done, including
 * potentially redispatching the command immediately (e.g. for remote
 * commands).
 *
 * @return bool
 *   TRUE if the command was handled remotely.
 */
function drush_preflight_command_dispatch() {
  $interactive = drush_get_option('interactive', FALSE);

  // The command will be executed remotely if the --remote-host flag
  // is set; note that if a site alias is provided on the command line,
  // and the site alias references a remote server, then the --remote-host
  // option will be set when the site alias is processed.
  // @see drush_sitealias_check_arg_and_site_set and _drush_sitealias_set_context_by_name
  $remote_host = drush_get_option('remote-host');
  $site_list = drush_get_option('site-list');
  // Get the command early so that we can allow commands to directly handle remote aliases if they wish
  $command = drush_parse_command();
  drush_command_default_options($command);

  $args = drush_get_arguments();
  $command_name = array_shift($args);
  $root = Drush::bootstrapManager()->getRoot();
  $is_local = drush_get_option('local');
  $values = NULL;

  $local_drush = drush_get_option('drush-script');
  if (!empty($local_drush)) {
    if (!drush_is_absolute_path($local_drush)) {
      $local_drush = $root . DIRECTORY_SEPARATOR . $local_drush;
    }
    $local_drush = realpath($local_drush);
  }

  $drupal_root_from_alias = drush_get_option('root', $root, 'alias');
  $shouldRedispatch = (!isset($remote_host) && !empty($root)) && ($root != $drupal_root_from_alias);

  if (!isset($remote_host) && !empty($root) && !empty($local_drush) && empty($is_local)) {
    $this_drush = drush_find_drush();
    // If there is a local Drush selected, and it is not the
    // same Drush that is currently running, redispatch to it.
    // We assume that if the current Drush is nested inside
    // the current Drupal root (or, more specifically, the
    // current Drupal root's parent), then it is a site-local Drush.
    // We avoid redispatching in that instance to prevent an
    // infinite loop.
    if (file_exists($local_drush) && !drush_is_nested_directory(dirname($root), $this_drush)) {
      $uri = drush_get_context('DRUSH_SELECTED_URI');
      $shouldRedispatch = true;
    }
  }

  if ($shouldRedispatch) {
    $aditional_options = [
      'root' => $drupal_root_from_alias,
    ];
    if (!empty($uri)) {
      $aditional_options['uri'] = $uri;
    }
    // We need to chdir to the Drupal root here, for the
    // benefit of the Drush wrapper.
    chdir($root);
    $values = drush_do_command_redispatch(is_array($command) ? $command : $command_name, $args, NULL, NULL, $local_drush, TRUE, $aditional_options);
    return drush_preflight_command_dispatch_exit($values);
  }

  // If the command sets the 'handle-remote-commands' flag, then we will short-circuit
  // remote command dispatching and site-list command dispatching, and always let
  // the command handler run on the local machine.
  if (is_array($command) && !empty($command['handle-remote-commands'])) {
    return FALSE;
  }
  if (isset($remote_host)) {
    $remote_user = drush_get_option('remote-user');

    // Force interactive mode if there is a single remote target.  #interactive is added by drush_do_command_redispatch
    $user_interactive = drush_get_option('interactive');
    drush_set_option('interactive', TRUE);
    $values = drush_do_command_redispatch(is_array($command) ? $command : $command_name, $args, $remote_host, $remote_user, $user_interactive);
    return drush_preflight_command_dispatch_exit($values);
  }
  // If the --site-list flag is set, then we will execute the specified
  // command once for every site listed in the site list.
  if (isset($site_list)) {
    if (!is_array($site_list)) {
      $site_list = explode(',', $site_list);
    }
    $site_record = array('site-list' => $site_list);
    $args = drush_get_arguments();

    if (!drush_get_context('DRUSH_SIMULATE') && !$interactive  && !drush_get_context('DRUSH_AFFIRMATIVE') && !drush_get_context('DRUSH_QUIET')) {
      drush_print(dt("You are about to execute '!command' non-interactively (--yes forced) on all of the following targets:", array('!command' => implode(" ", $args))));
      foreach ($site_list as $one_destination) {
        drush_print(dt('  !target', array('!target' => $one_destination)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
        drush_user_abort();
        return TRUE;
      }
    }
    $command_name = array_shift($args);
    $multi_options = drush_redispatch_get_options();
    $backend_options = array();
    if (drush_get_option('pipe') || drush_get_option('interactive')) {
      $backend_options['interactive'] = TRUE;
    }
    if (drush_get_option('no-label', FALSE)) {
      $backend_options['no-label'] = TRUE;
    }

    $values = drush_invoke_process($site_record, $command_name, $args, $multi_options, $backend_options);
    return drush_preflight_command_dispatch_exit($values);
  }
  return FALSE;
}

function drush_preflight_command_dispatch_exit($values) {
  if (isset($values)) {
    if (is_array($values) && ($values['error_status'] > 0)) {
      // Force an error result code.  Note that drush_shutdown() will still run.
      drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
      exit($values['error_status']);
    }
    return TRUE;
  }
  return FALSE;
}

/**
 * Look for instances of arguments or parameters that
 * start with "~/".  Replace these with "$HOME/".
 *
 * Note that this function is called _after_ Drush does
 * its redispatch checks; tildes are passed through
 * unmodified on a redispatch, and are only expanded when
 * a command is handled locally.
 */
function drush_preflight_tilde_expansion(&$command) {
  // Skip tilde expansion for commands that explicitly
  // turn it off via $command['tilde-expansion'] = FALSE.
  if ($command['tilde-expansion']) {
    $cli =& drush_get_context('cli');
    $match = '#^~/#';
    $replacement = drush_server_home() . '/';
    foreach ($cli as $key => $value) {
      if (is_string($value) && preg_match($match, $value)) {
        $cli[$key] = preg_replace($match, $replacement, $value);
      }
    }
    $command['arguments'] = array_map(function($value) use($match, $replacement) { return is_string($value) ? preg_replace($match, $replacement, $value) : $value; } , $command['arguments']);
  }
}

/**
 * We set this context to let the shutdown function know we reached the end of drush_main().
 *
 * @see drush_main()
 */
function drush_postflight() {
  drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);
}

/**
 * Shutdown function for use while Drush and Drupal are bootstrapping and to return any
 * registered errors.
 *
 * The shutdown command checks whether certain options are set to reliably
 * detect and log some common Drupal initialization errors.
 *
 * If the command is being executed with the --backend option, the script
 * will return a json string containing the options and log information
 * used by the script.
 *
 * The command will exit with '1' if it was successfully executed, and the
 * result of drush_get_error() if it wasn't.
 */
function drush_shutdown() {
  // Mysteriously make $user available during sess_write(). Avoids a NOTICE.
  global $user;

  if (!drush_get_context('DRUSH_EXECUTION_COMPLETED', FALSE) && !drush_get_context('DRUSH_USER_ABORT', FALSE)) {
    $php_error_message = '';
    if ($error = error_get_last()) {
      $php_error_message = "\n" . dt('Error: !message in !file, line !line', array('!message' => $error['message'], '!file' => $error['file'], '!line' => $error['line']));
    }
    // We did not reach the end of the drush_main function,
    // this generally means somewhere in the code a call to exit(),
    // was made. We catch this, so that we can trigger an error in
    // those cases.
    drush_set_error("DRUSH_NOT_COMPLETED", dt("Drush command terminated abnormally due to an unrecoverable error.!message", array('!message' => $php_error_message)));
    // Attempt to give the user some advice about how to fix the problem
    _drush_postmortem();
  }

  // @todo Ask the bootstrap object (or maybe dispatch) how far we got.
  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if (drush_get_context('DRUSH_BOOTSTRAPPING')) {
    switch ($phase) {
      case DRUSH_BOOTSTRAP_DRUPAL_FULL :
        ob_end_clean();
        _drush_log_drupal_messages();
        $message = dt("Drush was not able to start (bootstrap) Drupal.\n");
        $message .= dt("Hint: This error can only occur once the database connection has already been successfully initiated, therefore this error generally points to a site configuration issue, and not a problem connecting to the database.\n");
        $message .= dt('More information may be available by running `drush status`');
        drush_set_error($message);
        break;
    }
  }

  if (drush_get_context('DRUSH_BACKEND', FALSE)) {
    drush_backend_output();
  }
  elseif (drush_get_context('DRUSH_QUIET', FALSE)) {
    ob_end_clean();
  }

  // This way drush_return_status() will always be the last shutdown function (unless other shutdown functions register shutdown functions...)
  // and won't prevent other registered shutdown functions (IE from numerous cron methods) from running by calling exit() before they get a chance.
  register_shutdown_function('drush_return_status');
}

/**
 * Shutdown function to save code coverage data.
 */
function drush_coverage_shutdown() {
  if ($file_name = drush_get_context('DRUSH_CODE_COVERAGE', FALSE)) {
    $data = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();

    // If coverage dump file contains anything, merge in the old data before
    // saving. This happens if the current drush command invoked another drush
    // command.
    if (file_exists($file_name) && $content = file_get_contents($file_name)) {
      $merge_data = unserialize($content);
      if (is_array($merge_data)) {
        foreach ($merge_data as $file => $lines) {
          if (!isset($data[$file])) {
            $data[$file] = $lines;
          }
          else {
            foreach ($lines as $num => $executed) {
              if (!isset($data[$file][$num])) {
                $data[$file][$num] = $executed;
              }
              else {
                $data[$file][$num] = ($executed == 1 ? $executed : $data[$file][$num]);
              }
            }
          }
        }
      }
    }

    file_put_contents($file_name, serialize($data));
  }
}

function drush_return_status() {
  // If a specific exit code was set, then use it.
  $exit_code = drush_get_context('DRUSH_EXIT_CODE');
  if (empty($exit_code)) {
    $exit_code = (drush_get_error()) ? DRUSH_FRAMEWORK_ERROR : DRUSH_SUCCESS;
  }

  exit($exit_code);
}
