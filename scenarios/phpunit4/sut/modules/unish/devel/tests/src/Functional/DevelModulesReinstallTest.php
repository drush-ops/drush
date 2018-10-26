<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests reinstall modules.
 *
 * @group devel
 */
class DevelModulesReinstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['devel'];

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * Set up test.
   */
  protected function setUp() {
    parent::setUp();

    $web_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($web_user);
  }

  /**
   * Reinstall modules.
   */
  public function testDevelReinstallModules() {
    // Minimal profile enables only dblog, block and node.
    $modules = ['dblog', 'block'];

    // Needed for compare correctly the message.
    sort($modules);

    $this->drupalGet('devel/reinstall');

    // Prepare field data in an associative array
    $edit = [];
    foreach ($modules as $module) {
      $edit["reinstall[$module]"] = TRUE;
    }

    $this->drupalPostForm('devel/reinstall', $edit, t('Reinstall'));
    $this->assertText(t('Uninstalled and installed: @names.', ['@names' => implode(', ', $modules)]));
  }

}
