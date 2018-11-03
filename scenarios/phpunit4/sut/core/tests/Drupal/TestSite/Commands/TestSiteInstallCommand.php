<?php

namespace Drupal\TestSite\Commands;

use Drupal\Core\Database\Database;
use Drupal\Core\Test\FunctionalTestSetupTrait;
use Drupal\Core\Test\TestDatabase;
use Drupal\Core\Test\TestSetupTrait;
use Drupal\TestSite\TestSetupInterface;
use Drupal\Tests\RandomGeneratorTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to create a test Drupal site.
 *
 * @internal
 */
class TestSiteInstallCommand extends Command {

  use FunctionalTestSetupTrait {
    installParameters as protected installParametersTrait;
  }
  use RandomGeneratorTrait;
  use TestSetupTrait {
    changeDatabasePrefix as protected changeDatabasePrefixTrait;
  }

  /**
   * The install profile to use.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * Time limit in seconds for the test.
   *
   * Used by \Drupal\Core\Test\FunctionalTestSetupTrait::prepareEnvironment().
   *
   * @var int
   */
  protected $timeLimit = 500;

  /**
   * The database prefix of this test run.
   *
   * @var string
   */
  protected $databasePrefix;

  /**
   * The language to install the site in.
   *
   * @var string
   */
  protected $langcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this->setName('install')
      ->setDescription('Creates a test Drupal site')
      ->setHelp('The details to connect to the test site created will be displayed upon success. It will contain the database prefix and the user agent.')
      ->addOption('setup-file', NULL, InputOption::VALUE_OPTIONAL, 'The path to a PHP file containing a class to setup configuration used by the test, for example, core/tests/Drupal/TestSite/TestSiteInstallTestScript.php.')
      ->addOption('db-url', NULL, InputOption::VALUE_OPTIONAL, 'URL for database. Defaults to the environment variable SIMPLETEST_DB.', getenv('SIMPLETEST_DB'))
      ->addOption('base-url', NULL, InputOption::VALUE_OPTIONAL, 'Base URL for site under test. Defaults to the environment variable SIMPLETEST_BASE_URL.', getenv('SIMPLETEST_BASE_URL'))
      ->addOption('install-profile', NULL, InputOption::VALUE_OPTIONAL, 'Install profile to install the site in. Defaults to testing.', 'testing')
      ->addOption('langcode', NULL, InputOption::VALUE_OPTIONAL, 'The language to install the site in. Defaults to en.', 'en')
      ->addOption('json', NULL, InputOption::VALUE_NONE, 'Output test site connection details in JSON.')
      ->addUsage('--setup-file core/tests/Drupal/TestSite/TestSiteInstallTestScript.php --json')
      ->addUsage('--install-profile demo_umami --langcode fr')
      ->addUsage('--base-url "http://example.com" --db-url "mysql://username:password@localhost/databasename#table_prefix"');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Determines and validates the setup class prior to installing a database
    // to avoid creating unnecessary sites.
    $root = dirname(dirname(dirname(dirname(dirname(__DIR__)))));
    chdir($root);
    $class_name = $this->getSetupClass($input->getOption('setup-file'));
    // Ensure we can install a site in the sites/simpletest directory.
    $this->ensureDirectory($root);

    $db_url = $input->getOption('db-url');
    $base_url = $input->getOption('base-url');
    putenv("SIMPLETEST_DB=$db_url");
    putenv("SIMPLETEST_BASE_URL=$base_url");

    // Manage site fixture.
    $this->setup($input->getOption('install-profile'), $class_name, $input->getOption('langcode'));

    $user_agent = drupal_generate_test_ua($this->databasePrefix);
    if ($input->getOption('json')) {
      $output->writeln(json_encode([
        'db_prefix' => $this->databasePrefix,
        'user_agent' => $user_agent,
        'site_path' => $this->siteDirectory,
      ]));
    }
    else {
      $output->writeln('<info>Successfully installed a test site</info>');
      $io = new SymfonyStyle($input, $output);
      $io->table([], [
        ['Database prefix', $this->databasePrefix],
        ['User agent', $user_agent],
        ['Site path', $this->siteDirectory],
      ]);
    }
  }

  /**
   * Gets the setup class.
   *
   * @param string|null $file
   *   The file to get the setup class from.
   *
   * @return string|null
   *   The setup class contained in the provided $file.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the file does not exist, does not contain a class or the class
   *   does not implement \Drupal\TestSite\TestSetupInterface.
   */
  protected function getSetupClass($file) {
    if ($file === NULL) {
      return;
    }
    if (!file_exists($file)) {
      throw new \InvalidArgumentException("The file $file does not exist.");
    }

    $classes = get_declared_classes();
    include_once $file;
    $new_classes = array_values(array_diff(get_declared_classes(), $classes));
    if (empty($new_classes)) {
      throw new \InvalidArgumentException("The file $file does not contain a class.");
    }
    $class = array_pop($new_classes);

    if (!is_subclass_of($class, TestSetupInterface::class)) {
      throw new \InvalidArgumentException("The class $class contained in $file needs to implement \Drupal\TestSite\TestSetupInterface");
    }
    return $class;
  }

  /**
   * Ensures that the sites/simpletest directory exists and is writable.
   *
   * @param string $root
   *   The Drupal root.
   */
  protected function ensureDirectory($root) {
    if (!is_writable($root . '/sites/simpletest')) {
      if (!@mkdir($root . '/sites/simpletest')) {
        throw new \RuntimeException($root . '/sites/simpletest must exist and be writable to install a test site');
      }
    }
  }

  /**
   * Creates a test drupal installation.
   *
   * @param string $profile
   *   (optional) The installation profile to use.
   * @param string $setup_class
   *   (optional) Setup class. A PHP class to setup configuration used by the
   *   test.
   * @param string $langcode
   *   (optional) The language to install the site in.
   */
  public function setup($profile = 'testing', $setup_class = NULL, $langcode = 'en') {
    $this->profile = $profile;
    $this->langcode = $langcode;
    $this->setupBaseUrl();
    $this->prepareEnvironment();
    $this->installDrupal();

    if ($setup_class) {
      $this->executeSetupClass($setup_class);
    }
  }

  /**
   * Installs Drupal into the test site.
   */
  protected function installDrupal() {
    $this->initUserSession();
    $this->prepareSettings();
    $this->doInstall();
    $this->initSettings();
    $container = $this->initKernel(\Drupal::request());
    $this->initConfig($container);
    $this->installModulesFromClassProperty($container);
    $this->rebuildAll();
  }

  /**
   * Uses the setup file to configure Drupal.
   *
   * @param string $class
   *   The fully qualified class name, which should set up Drupal for tests. For
   *   example this class could create content types and fields or install
   *   modules. The class needs to implement TestSetupInterface.
   *
   * @see \Drupal\TestSite\TestSetupInterface
   */
  protected function executeSetupClass($class) {
    /** @var \Drupal\TestSite\TestSetupInterface $instance */
    $instance = new $class();
    $instance->setup();
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters() {
    $parameters = $this->installParametersTrait();
    $parameters['parameters']['langcode'] = $this->langcode;
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  protected function changeDatabasePrefix() {
    // Ensure that we use the database from SIMPLETEST_DB environment variable.
    Database::removeConnection('default');
    $this->changeDatabasePrefixTrait();
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareDatabasePrefix() {
    // Override this method so that we can force a lock to be created.
    $test_db = new TestDatabase(NULL, TRUE);
    $this->siteDirectory = $test_db->getTestSitePath();
    $this->databasePrefix = $test_db->getDatabasePrefix();
  }

}
