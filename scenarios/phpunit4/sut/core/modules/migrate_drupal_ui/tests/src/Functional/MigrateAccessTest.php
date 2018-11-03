<?php

namespace Drupal\Tests\migrate_drupal_ui\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that only user 1 can access the migrate UI.
 *
 * @group migrate_drupal_ui
 */
class MigrateAccessTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['migrate_drupal_ui'];

  /**
   * Tests that only user 1 can access the migrate UI.
   */
  public function testAccess() {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('upgrade');
    $this->assertResponse(200);
    $this->assertText(t('Upgrade'));

    $user = $this->createUser(['administer software updates']);
    $this->drupalLogin($user);
    $this->drupalGet('upgrade');
    $this->assertResponse(403);
    $this->assertNoText(t('Upgrade'));
  }

}
