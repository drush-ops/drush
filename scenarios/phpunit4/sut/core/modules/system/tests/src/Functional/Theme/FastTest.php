<?php

namespace Drupal\Tests\system\Functional\Theme;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests autocompletion not loading registry.
 *
 * @group Theme
 */
class FastTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['theme_test'];

  protected function setUp() {
    parent::setUp();
    $this->account = $this->drupalCreateUser(['access user profiles']);
  }

  /**
   * Tests access to user autocompletion and verify the correct results.
   */
  public function testUserAutocomplete() {
    $this->drupalLogin($this->account);
    $this->drupalGet('user/autocomplete', ['query' => ['q' => $this->account->getUsername()]]);
    $this->assertRaw($this->account->getUsername());
    $this->assertNoText('registry initialized', 'The registry was not initialized');
  }

}
