<?php

namespace Drupal\Tests\system\Functional\Routing;

use Drupal\Tests\BrowserTestBase;

/**
 * Function Tests for the routing permission system.
 *
 * @group Routing
 */
class RouterPermissionTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['router_test'];

  /**
   * Tests permission requirements on routes.
   */
  public function testPermissionAccess() {
    $path = 'router_test/test7';
    $this->drupalGet($path);
    $this->assertResponse(403, "Access denied for a route where we don't have a permission");

    $this->drupalGet('router_test/test8');
    $this->assertResponse(403, 'Access denied by default if no access specified');

    $user = $this->drupalCreateUser(['access test7']);
    $this->drupalLogin($user);
    $this->drupalGet('router_test/test7');
    $this->assertResponse(200);
    $this->assertNoRaw('Access denied');
    $this->assertRaw('test7text', 'The correct string was returned because the route was successful.');
  }

}
