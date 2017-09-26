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
 * This function is still called by drush-launcher. It is no longer used by
 * Drush itself.
 */
function drush_main() {
  require dirname(__DIR__) . '/drush.php';
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

  // Signal that our old-style contexts are in use.
  drush_set_context('DRUSH_LEGACY_CONTEXT', true);

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
  // Avoid doing anything if our container has not been initialized yet.
  if (!\Drush\Drush::hasContainer()) {
    return;
  }

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

  // @todo Allow the bootstrap objects to register shutdown handlers on
  // the bootstrap manager, and remove them when the bootstrap phase succeeds.
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

  if (\Drush\Drush::backend()) {
    drush_backend_output();
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
