<?php

namespace Drupal\Core\Test;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel for run-tests.sh.
 */
class TestRunnerKernel extends DrupalKernel {

  /**
   * {@inheritdoc}
   */
  public static function createFromRequest(Request $request, $class_loader, $environment = 'test_runner', $allow_dumping = TRUE, $app_root = NULL) {
    return parent::createFromRequest($request, $class_loader, $environment, $allow_dumping, $app_root);
  }

  /**
   * {@inheritdoc}
   */
  public function __construct($environment, $class_loader, $allow_dumping = FALSE, $app_root = NULL) {
    // Force $allow_dumping to FALSE, because the test runner kernel should
    // always have to rebuild its container, and potentially avoid isolation
    // issues against the tests.
    parent::__construct($environment, $class_loader, FALSE, $app_root);

    // Prime the module list and corresponding Extension objects.
    // @todo Remove System module. Needed because
    //   \Drupal\Core\Datetime\DateFormatter has a (needless) dependency on the
    //   'date_format' entity, so calls to format_date()/format_interval() cause
    //   a plugin not found exception.
    $this->moduleList = [
      'system' => 0,
      'simpletest' => 0,
    ];
    $this->moduleData = [
      'system' => new Extension($this->root, 'module', 'core/modules/system/system.info.yml', 'system.module'),
      'simpletest' => new Extension($this->root, 'module', 'core/modules/simpletest/simpletest.info.yml', 'simpletest.module'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function boot() {
    // Ensure that required Settings exist.
    if (!Settings::getAll()) {
      new Settings([
        'hash_salt' => 'run-tests',
        'container_yamls' => [],
        // If there is no settings.php, then there is no parent site. In turn,
        // there is no public files directory; use a custom public files path.
        'file_public_path' => 'sites/default/files',
      ]);
    }

    // Remove Drupal's error/exception handlers; they are designed for HTML
    // and there is no storage nor a (watchdog) logger here.
    restore_error_handler();
    restore_exception_handler();

    // In addition, ensure that PHP errors are not hidden away in logs.
    ini_set('display_errors', TRUE);

    parent::boot();

    $this->getContainer()->get('module_handler')->loadAll();

    $this->getContainer()->get('test_discovery')->registerTestNamespaces();

    // Register stream wrappers.
    $this->getContainer()->get('stream_wrapper_manager')->register();

    // Create the build/artifacts directory if necessary.
    include_once $this->getAppRoot() . '/core/includes/file.inc';
    if (!is_dir('public://simpletest')) {
      mkdir('public://simpletest', 0777, TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function discoverServiceProviders() {
    parent::discoverServiceProviders();
    // The test runner does not require an installed Drupal site to exist.
    // Therefore, its environment is identical to that of the early installer.
    $this->serviceProviderClasses['app']['Test'] = 'Drupal\Core\Installer\InstallerServiceProvider';
  }

}
