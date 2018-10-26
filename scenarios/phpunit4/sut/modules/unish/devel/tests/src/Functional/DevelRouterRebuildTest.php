<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests routes rebuild.
 *
 * @group devel
 */
class DevelRouterRebuildTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel', 'devel_test'];

  /**
   * Test routes rebuild.
   */
  public function testRouterRebuildConfirmForm() {
    // Reset the state flag.
    \Drupal::state()->set('devel_test_route_rebuild', NULL);

    $this->drupalGet('devel/menu/reset');
    $this->assertSession()->statusCodeEquals(403);

    $web_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($web_user);

    $this->drupalGet('devel/menu/reset');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Are you sure you want to rebuild the router?');
    $route_rebuild_state = \Drupal::state()->get('devel_test_route_rebuild');
    $this->assertEmpty($route_rebuild_state);

    $this->drupalPostForm('devel/menu/reset', [], t('Rebuild'));
    $this->assertSession()->pageTextContains('The router has been rebuilt.');
    $route_rebuild_state = \Drupal::state()->get('devel_test_route_rebuild');
    $this->assertEquals('Router rebuild fired', $route_rebuild_state);
  }

}
