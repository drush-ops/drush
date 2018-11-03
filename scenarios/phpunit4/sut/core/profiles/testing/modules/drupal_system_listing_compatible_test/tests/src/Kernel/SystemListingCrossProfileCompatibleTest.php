<?php

namespace Drupal\Tests\drupal_system_listing_compatible_test\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies that tests in installation profile modules are found and may use
 * another profile for running tests.
 *
 * @group drupal_system_listing_compatible_test
 */
class SystemListingCrossProfileCompatibleTest extends KernelTestBase {

  /**
   * Attempt to enable a module from the Testing profile.
   *
   * This test uses the Minimal profile, but enables a module from the Testing
   * profile to confirm that a different profile can be used for running tests.
   *
   * @var array
   */
  public static $modules = ['drupal_system_cross_profile_test'];

  /**
   * Use the Minimal profile.
   *
   * This test needs to use a different installation profile than the test which
   * asserts that this test is found.
   *
   * @see \Drupal\simpletest\Tests\InstallationProfileModuleTestsTest
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setInstallProfile($this->profile);
  }

  /**
   * Non-empty test* method required to executed the test case class.
   */
  public function testSystemListing() {
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $this->container->get('module_handler');
    $this->assertTrue($module_handler->moduleExists('drupal_system_cross_profile_test'), 'Module installed from different profile');
  }

}
