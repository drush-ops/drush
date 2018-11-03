<?php

namespace Drupal\Tests\system\Functional\ServiceProvider;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests service provider registration to the DIC.
 *
 * @group ServiceProvider
 */
class ServiceProviderWebTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['file', 'service_provider_test'];

  /**
   * Tests that module service providers get registered to the DIC.
   *
   * Also tests that services provided by module service providers get
   * registered to the DIC.
   */
  public function testServiceProviderRegistrationIntegration() {
    $this->assertTrue(\Drupal::hasService('service_provider_test_class'), 'The service_provider_test_class service has been registered to the DIC');
    // The event subscriber method in the test class calls
    // \Drupal\Core\Messenger\MessengerInterface::addStatus() with a message
    // saying it has fired. This will fire on every page request so it should
    // show up on the front page.
    $this->drupalGet('');
    $this->assertText(t('The service_provider_test event subscriber fired!'), 'The service_provider_test event subscriber fired');
  }

}
