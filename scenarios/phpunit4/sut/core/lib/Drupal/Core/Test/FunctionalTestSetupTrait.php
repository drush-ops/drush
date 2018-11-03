<?php

namespace Drupal\Core\Test;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\Development\ConfigSchemaChecker;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Tests\SessionTestTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Defines a trait for shared functional test setup functionality.
 */
trait FunctionalTestSetupTrait {

  use SessionTestTrait;
  use RefreshVariablesTrait;

  /**
   * The "#1" admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $rootUser;

  /**
   * The class loader to use for installation and initialization of setup.
   *
   * @var \Symfony\Component\Classloader\Classloader
   */
  protected $classLoader;

  /**
   * The config directories used in this test.
   */
  protected $configDirectories = [];

  /**
   * The flag to set 'apcu_ensure_unique_prefix' setting.
   *
   * Wide use of a unique prefix can lead to problems with memory, if tests are
   * run with a concurrency higher than 1. Therefore, FALSE by default.
   *
   * @var bool
   *
   * @see \Drupal\Core\Site\Settings::getApcuPrefix().
   */
  protected $apcuEnsureUniquePrefix = FALSE;

  /**
   * Prepares site settings and services before installation.
   */
  protected function prepareSettings() {
    // Prepare installer settings that are not install_drupal() parameters.
    // Copy and prepare an actual settings.php, so as to resemble a regular
    // installation.
    // Not using File API; a potential error must trigger a PHP warning.
    $directory = DRUPAL_ROOT . '/' . $this->siteDirectory;
    copy(DRUPAL_ROOT . '/sites/default/default.settings.php', $directory . '/settings.php');

    // The public file system path is created during installation. Additionally,
    // during tests:
    // - The temporary directory is set and created by install_base_system().
    // - The private file directory is created post install by
    //   FunctionalTestSetupTrait::initConfig().
    // @see system_requirements()
    // @see TestBase::prepareEnvironment()
    // @see install_base_system()
    // @see \Drupal\Core\Test\FunctionalTestSetupTrait::initConfig()
    $settings['settings']['file_public_path'] = (object) [
      'value' => $this->publicFilesDirectory,
      'required' => TRUE,
    ];
    $settings['settings']['file_private_path'] = (object) [
      'value' => $this->privateFilesDirectory,
      'required' => TRUE,
    ];
    // Save the original site directory path, so that extensions in the
    // site-specific directory can still be discovered in the test site
    // environment.
    // @see \Drupal\Core\Extension\ExtensionDiscovery::scan()
    $settings['settings']['test_parent_site'] = (object) [
      'value' => $this->originalSite,
      'required' => TRUE,
    ];
    // Add the parent profile's search path to the child site's search paths.
    // @see \Drupal\Core\Extension\ExtensionDiscovery::getProfileDirectories()
    $settings['conf']['simpletest.settings']['parent_profile'] = (object) [
      'value' => $this->originalProfile,
      'required' => TRUE,
    ];
    $settings['settings']['apcu_ensure_unique_prefix'] = (object) [
      'value' => $this->apcuEnsureUniquePrefix,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    // Allow for test-specific overrides.
    $settings_testing_file = DRUPAL_ROOT . '/' . $this->originalSite . '/settings.testing.php';
    if (file_exists($settings_testing_file)) {
      // Copy the testing-specific settings.php overrides in place.
      copy($settings_testing_file, $directory . '/settings.testing.php');
      // Add the name of the testing class to settings.php and include the
      // testing specific overrides.
      file_put_contents($directory . '/settings.php', "\n\$test_class = '" . get_class($this) . "';\n" . 'include DRUPAL_ROOT . \'/\' . $site_path . \'/settings.testing.php\';' . "\n", FILE_APPEND);
    }
    $settings_services_file = DRUPAL_ROOT . '/' . $this->originalSite . '/testing.services.yml';
    if (!file_exists($settings_services_file)) {
      // Otherwise, use the default services as a starting point for overrides.
      $settings_services_file = DRUPAL_ROOT . '/sites/default/default.services.yml';
    }
    // Copy the testing-specific service overrides in place.
    copy($settings_services_file, $directory . '/services.yml');
    if ($this->strictConfigSchema) {
      // Add a listener to validate configuration schema on save.
      $yaml = new SymfonyYaml();
      $content = file_get_contents($directory . '/services.yml');
      $services = $yaml->parse($content);
      $services['services']['simpletest.config_schema_checker'] = [
        'class' => ConfigSchemaChecker::class,
        'arguments' => ['@config.typed', $this->getConfigSchemaExclusions()],
        'tags' => [['name' => 'event_subscriber']],
      ];
      file_put_contents($directory . '/services.yml', $yaml->dump($services));
    }
    // Since Drupal is bootstrapped already, install_begin_request() will not
    // bootstrap again. Hence, we have to reload the newly written custom
    // settings.php manually.
    Settings::initialize(DRUPAL_ROOT, $this->siteDirectory, $this->classLoader);
  }

  /**
   * Rewrites the settings.php file of the test site.
   *
   * @param array $settings
   *   An array of settings to write out, in the format expected by
   *   drupal_rewrite_settings().
   *
   * @see drupal_rewrite_settings()
   */
  protected function writeSettings(array $settings) {
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    $filename = $this->siteDirectory . '/settings.php';
    // system_requirements() removes write permissions from settings.php
    // whenever it is invoked.
    // Not using File API; a potential error must trigger a PHP warning.
    chmod($filename, 0666);
    drupal_rewrite_settings($settings, $filename);
  }

  /**
   * Changes parameters in the services.yml file.
   *
   * @param string $name
   *   The name of the parameter.
   * @param string $value
   *   The value of the parameter.
   */
  protected function setContainerParameter($name, $value) {
    $filename = $this->siteDirectory . '/services.yml';
    chmod($filename, 0666);

    $services = Yaml::decode(file_get_contents($filename));
    $services['parameters'][$name] = $value;
    file_put_contents($filename, Yaml::encode($services));

    // Ensure that the cache is deleted for the yaml file loader.
    $file_cache = FileCacheFactory::get('container_yaml_loader');
    $file_cache->delete($filename);
  }

  /**
   * Rebuilds \Drupal::getContainer().
   *
   * Use this to update the test process's kernel with a new service container.
   * For example, when the list of enabled modules is changed via the internal
   * browser the test process's kernel has a service container with an out of
   * date module list.
   *
   * @see TestBase::prepareEnvironment()
   * @see TestBase::restoreEnvironment()
   *
   * @todo Fix https://www.drupal.org/node/2021959 so that module enable/disable
   *   changes are immediately reflected in \Drupal::getContainer(). Until then,
   *   tests can invoke this workaround when requiring services from newly
   *   enabled modules to be immediately available in the same request.
   */
  protected function rebuildContainer() {
    // Rebuild the kernel and bring it back to a fully bootstrapped state.
    $this->container = $this->kernel->rebuildContainer();

    // Make sure the url generator has a request object, otherwise calls to
    // $this->drupalGet() will fail.
    $this->prepareRequestForGenerator();
  }

  /**
   * Resets all data structures after having enabled new modules.
   *
   * This method is called by FunctionalTestSetupTrait::rebuildAll() after
   * enabling the requested modules. It must be called again when additional
   * modules are enabled later.
   *
   * @see \Drupal\Core\Test\FunctionalTestSetupTrait::rebuildAll()
   * @see \Drupal\Tests\BrowserTestBase::installDrupal()
   * @see \Drupal\simpletest\WebTestBase::setUp()
   */
  protected function resetAll() {
    // Clear all database and static caches and rebuild data structures.
    drupal_flush_all_caches();
    $this->container = \Drupal::getContainer();

    // Reset static variables and reload permissions.
    $this->refreshVariables();
  }

  /**
   * Creates a mock request and sets it on the generator.
   *
   * This is used to manipulate how the generator generates paths during tests.
   * It also ensures that calls to $this->drupalGet() will work when running
   * from run-tests.sh because the url generator no longer looks at the global
   * variables that are set there but relies on getting this information from a
   * request object.
   *
   * @param bool $clean_urls
   *   Whether to mock the request using clean urls.
   * @param array $override_server_vars
   *   An array of server variables to override.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The mocked request object.
   */
  protected function prepareRequestForGenerator($clean_urls = TRUE, $override_server_vars = []) {
    $request = Request::createFromGlobals();
    $server = $request->server->all();
    if (basename($server['SCRIPT_FILENAME']) != basename($server['SCRIPT_NAME'])) {
      // We need this for when the test is executed by run-tests.sh.
      // @todo Remove this once run-tests.sh has been converted to use a Request
      //   object.
      $cwd = getcwd();
      $server['SCRIPT_FILENAME'] = $cwd . '/' . basename($server['SCRIPT_NAME']);
      $base_path = rtrim($server['REQUEST_URI'], '/');
    }
    else {
      $base_path = $request->getBasePath();
    }
    if ($clean_urls) {
      $request_path = $base_path ? $base_path . '/user' : 'user';
    }
    else {
      $request_path = $base_path ? $base_path . '/index.php/user' : '/index.php/user';
    }
    $server = array_merge($server, $override_server_vars);

    $request = Request::create($request_path, 'GET', [], [], [], $server);
    // Ensure the request time is REQUEST_TIME to ensure that API calls
    // in the test use the right timestamp.
    $request->server->set('REQUEST_TIME', REQUEST_TIME);
    $this->container->get('request_stack')->push($request);

    // The request context is normally set by the router_listener from within
    // its KernelEvents::REQUEST listener. In the simpletest parent site this
    // event is not fired, therefore it is necessary to updated the request
    // context manually here.
    $this->container->get('router.request_context')->fromRequest($request);

    return $request;
  }

  /**
   * Execute the non-interactive installer.
   *
   * @see install_drupal()
   */
  protected function doInstall() {
    require_once DRUPAL_ROOT . '/core/includes/install.core.inc';
    install_drupal($this->classLoader, $this->installParameters());
  }

  /**
   * Initialize settings created during install.
   */
  protected function initSettings() {
    Settings::initialize(DRUPAL_ROOT, $this->siteDirectory, $this->classLoader);
    foreach ($GLOBALS['config_directories'] as $type => $path) {
      $this->configDirectories[$type] = $path;
    }

    // After writing settings.php, the installer removes write permissions
    // from the site directory. To allow drupal_generate_test_ua() to write
    // a file containing the private key for drupal_valid_test_ua(), the site
    // directory has to be writable.
    // TestBase::restoreEnvironment() will delete the entire site directory.
    // Not using File API; a potential error must trigger a PHP warning.
    chmod(DRUPAL_ROOT . '/' . $this->siteDirectory, 0777);

    // During tests, cacheable responses should get the debugging cacheability
    // headers by default.
    $this->setContainerParameter('http.response.debug_cacheability_headers', TRUE);
  }

  /**
   * Initialize various configurations post-installation.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  protected function initConfig(ContainerInterface $container) {
    $config = $container->get('config.factory');

    // Manually create the private directory.
    file_prepare_directory($this->privateFilesDirectory, FILE_CREATE_DIRECTORY);

    // Manually configure the test mail collector implementation to prevent
    // tests from sending out emails and collect them in state instead.
    // While this should be enforced via settings.php prior to installation,
    // some tests expect to be able to test mail system implementations.
    $config->getEditable('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    // By default, verbosely display all errors and disable all production
    // environment optimizations for all tests to avoid needless overhead and
    // ensure a sane default experience for test authors.
    // @see https://www.drupal.org/node/2259167
    $config->getEditable('system.logging')
      ->set('error_level', 'verbose')
      ->save();
    $config->getEditable('system.performance')
      ->set('css.preprocess', FALSE)
      ->set('js.preprocess', FALSE)
      ->save();

    // Set an explicit time zone to not rely on the system one, which may vary
    // from setup to setup. The Australia/Sydney time zone is chosen so all
    // tests are run using an edge case scenario (UTC10 and DST). This choice
    // is made to prevent time zone related regressions and reduce the
    // fragility of the testing system in general.
    $config->getEditable('system.date')
      ->set('timezone.default', 'Australia/Sydney')
      ->save();
  }

  /**
   * Initializes user 1 for the site to be installed.
   */
  protected function initUserSession() {
    $password = $this->randomMachineName();
    // Define information about the user 1 account.
    $this->rootUser = new UserSession([
      'uid' => 1,
      'name' => 'admin',
      'mail' => 'admin@example.com',
      'pass_raw' => $password,
      'passRaw' => $password,
      'timezone' => date_default_timezone_get(),
    ]);

    // The child site derives its session name from the database prefix when
    // running web tests.
    $this->generateSessionName($this->databasePrefix);
  }

  /**
   * Initializes the kernel after installation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container.
   */
  protected function initKernel(Request $request) {
    $this->kernel = DrupalKernel::createFromRequest($request, $this->classLoader, 'prod', TRUE);
    $this->kernel->prepareLegacyRequest($request);
    // Force the container to be built from scratch instead of loaded from the
    // disk. This forces us to not accidentally load the parent site.
    return $this->kernel->rebuildContainer();
  }

  /**
   * Install modules defined by `static::$modules`.
   *
   * To install test modules outside of the testing environment, add
   * @code
   * $settings['extension_discovery_scan_tests'] = TRUE;
   * @endcode
   * to your settings.php.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   */
  protected function installModulesFromClassProperty(ContainerInterface $container) {
    $class = get_class($this);
    $modules = [];
    while ($class) {
      if (property_exists($class, 'modules')) {
        $modules = array_merge($modules, $class::$modules);
      }
      $class = get_parent_class($class);
    }
    if ($modules) {
      $modules = array_unique($modules);
      try {
        $success = $container->get('module_installer')->install($modules, TRUE);
        $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => implode(', ', $modules)]));
      }
      catch (MissingDependencyException $e) {
        // The exception message has all the details.
        $this->fail($e->getMessage());
      }

      $this->rebuildContainer();
    }
  }

  /**
   * Resets and rebuilds the environment after setup.
   */
  protected function rebuildAll() {
    // Reset/rebuild all data structures after enabling the modules, primarily
    // to synchronize all data structures and caches between the test runner and
    // the child site.
    // @see \Drupal\Core\DrupalKernel::bootCode()
    // @todo Test-specific setUp() methods may set up further fixtures; find a
    //   way to execute this after setUp() is done, or to eliminate it entirely.
    $this->resetAll();
    $this->kernel->prepareLegacyRequest(\Drupal::request());

    // Explicitly call register() again on the container registered in \Drupal.
    // @todo This should already be called through
    //   DrupalKernel::prepareLegacyRequest() -> DrupalKernel::boot() but that
    //   appears to be calling a different container.
    $this->container->get('stream_wrapper_manager')->register();
  }

  /**
   * Returns the parameters that will be used when Simpletest installs Drupal.
   *
   * @see install_drupal()
   * @see install_state_defaults()
   *
   * @return array
   *   Array of parameters for use in install_drupal().
   */
  protected function installParameters() {
    $connection_info = Database::getConnectionInfo();
    $driver = $connection_info['default']['driver'];
    $connection_info['default']['prefix'] = $connection_info['default']['prefix']['default'];
    unset($connection_info['default']['driver']);
    unset($connection_info['default']['namespace']);
    unset($connection_info['default']['pdo']);
    unset($connection_info['default']['init_commands']);
    // Remove database connection info that is not used by SQLite.
    if ($driver === 'sqlite') {
      unset($connection_info['default']['username']);
      unset($connection_info['default']['password']);
      unset($connection_info['default']['host']);
      unset($connection_info['default']['port']);
    }
    $parameters = [
      'interactive' => FALSE,
      'parameters' => [
        'profile' => $this->profile,
        'langcode' => 'en',
      ],
      'forms' => [
        'install_settings_form' => [
          'driver' => $driver,
          $driver => $connection_info['default'],
        ],
        'install_configure_form' => [
          'site_name' => 'Drupal',
          'site_mail' => 'simpletest@example.com',
          'account' => [
            'name' => $this->rootUser->name,
            'mail' => $this->rootUser->getEmail(),
            'pass' => [
              'pass1' => isset($this->rootUser->pass_raw) ? $this->rootUser->pass_raw : $this->rootUser->passRaw,
              'pass2' => isset($this->rootUser->pass_raw) ? $this->rootUser->pass_raw : $this->rootUser->passRaw,
            ],
          ],
          // form_type_checkboxes_value() requires NULL instead of FALSE values
          // for programmatic form submissions to disable a checkbox.
          'enable_update_status_module' => NULL,
          'enable_update_status_emails' => NULL,
        ],
      ],
    ];

    // If we only have one db driver available, we cannot set the driver.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    if (count($this->getDatabaseTypes()) == 1) {
      unset($parameters['forms']['install_settings_form']['driver']);
    }
    return $parameters;
  }

  /**
   * Sets up the base URL based upon the environment variable.
   *
   * @throws \Exception
   *   Thrown when no SIMPLETEST_BASE_URL environment variable is provided.
   */
  protected function setupBaseUrl() {
    global $base_url;

    // Get and set the domain of the environment we are running our test
    // coverage against.
    $base_url = getenv('SIMPLETEST_BASE_URL');
    if (!$base_url) {
      throw new \Exception(
        'You must provide a SIMPLETEST_BASE_URL environment variable to run some PHPUnit based functional tests.'
      );
    }

    // Setup $_SERVER variable.
    $parsed_url = parse_url($base_url);
    $host = $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');
    $path = isset($parsed_url['path']) ? rtrim(rtrim($parsed_url['path']), '/') : '';
    $port = isset($parsed_url['port']) ? $parsed_url['port'] : 80;

    $this->baseUrl = $base_url;

    // If the passed URL schema is 'https' then setup the $_SERVER variables
    // properly so that testing will run under HTTPS.
    if ($parsed_url['scheme'] === 'https') {
      $_SERVER['HTTPS'] = 'on';
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_ADDR'] = '127.0.0.1';
    $_SERVER['SERVER_PORT'] = $port;
    $_SERVER['SERVER_SOFTWARE'] = NULL;
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['REQUEST_URI'] = $path . '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = $path . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $path . '/index.php';
    $_SERVER['PHP_SELF'] = $path . '/index.php';
    $_SERVER['HTTP_USER_AGENT'] = 'Drupal command line';
  }

  /**
   * Prepares the current environment for running the test.
   *
   * Also sets up new resources for the testing environment, such as the public
   * filesystem and configuration directories.
   *
   * This method is private as it must only be called once by
   * BrowserTestBase::setUp() (multiple invocations for the same test would have
   * unpredictable consequences) and it must not be callable or overridable by
   * test classes.
   */
  protected function prepareEnvironment() {
    // Bootstrap Drupal so we can use Drupal's built in functions.
    $this->classLoader = require __DIR__ . '/../../../../../autoload.php';
    $request = Request::createFromGlobals();
    $kernel = TestRunnerKernel::createFromRequest($request, $this->classLoader);
    // TestRunnerKernel expects the working directory to be DRUPAL_ROOT.
    chdir(DRUPAL_ROOT);
    $kernel->prepareLegacyRequest($request);
    $this->prepareDatabasePrefix();

    $this->originalSite = $kernel->findSitePath($request);

    // Create test directory ahead of installation so fatal errors and debug
    // information can be logged during installation process.
    file_prepare_directory($this->siteDirectory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Prepare filesystem directory paths.
    $this->publicFilesDirectory = $this->siteDirectory . '/files';
    $this->privateFilesDirectory = $this->siteDirectory . '/private';
    $this->tempFilesDirectory = $this->siteDirectory . '/temp';
    $this->translationFilesDirectory = $this->siteDirectory . '/translations';

    // Ensure the configImporter is refreshed for each test.
    $this->configImporter = NULL;

    // Unregister all custom stream wrappers of the parent site.
    $wrappers = \Drupal::service('stream_wrapper_manager')->getWrappers(StreamWrapperInterface::ALL);
    foreach ($wrappers as $scheme => $info) {
      stream_wrapper_unregister($scheme);
    }

    // Reset statics.
    drupal_static_reset();

    $this->container = NULL;

    // Unset globals.
    unset($GLOBALS['config_directories']);
    unset($GLOBALS['config']);
    unset($GLOBALS['conf']);

    // Log fatal errors.
    ini_set('log_errors', 1);
    ini_set('error_log', DRUPAL_ROOT . '/' . $this->siteDirectory . '/error.log');

    // Change the database prefix.
    $this->changeDatabasePrefix();

    // After preparing the environment and changing the database prefix, we are
    // in a valid test environment.
    drupal_valid_test_ua($this->databasePrefix);

    // Reset settings.
    new Settings([
      // For performance, simply use the database prefix as hash salt.
      'hash_salt' => $this->databasePrefix,
    ]);

    drupal_set_time_limit($this->timeLimit);

    // Save and clean the shutdown callbacks array because it is static cached
    // and will be changed by the test run. Otherwise it will contain callbacks
    // from both environments and the testing environment will try to call the
    // handlers defined by the original one.
    $callbacks = &drupal_register_shutdown_function();
    $this->originalShutdownCallbacks = $callbacks;
    $callbacks = [];
  }

  /**
   * Returns all supported database driver installer objects.
   *
   * This wraps drupal_get_database_types() for use without a current container.
   *
   * @return \Drupal\Core\Database\Install\Tasks[]
   *   An array of available database driver installer objects.
   */
  protected function getDatabaseTypes() {
    if (isset($this->originalContainer) && $this->originalContainer) {
      \Drupal::setContainer($this->originalContainer);
    }
    $database_types = drupal_get_database_types();
    if (isset($this->originalContainer) && $this->originalContainer) {
      \Drupal::unsetContainer();
    }
    return $database_types;
  }

}
