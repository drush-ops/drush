<?php

namespace Drupal\Tests\color\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests sanitizing color preview loaded from theme.
 *
 * @group color
 */
class ColorSafePreviewTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  public static $modules = ['color', 'color_test'];

  /**
   * A user with administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $bigUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create user.
    $this->bigUser = $this->drupalCreateUser(['administer themes']);
  }

  /**
   * Ensures color preview.html is sanitized.
   */
  public function testColorPreview() {
    // Install the color test theme.
    \Drupal::service('theme_handler')->install(['color_test_theme']);
    $this->drupalLogin($this->bigUser);

    // Markup is being printed from a HTML file located in:
    // core/modules/color/tests/modules/color_test/themes/color_test_theme/color/preview.html
    $url = Url::fromRoute('system.theme_settings_theme', ['theme' => 'color_test_theme']);
    $this->drupalGet($url);
    $this->assertText('TEST COLOR PREVIEW');

    $this->assertNoRaw('<script>alert("security filter test");</script>');
    $this->assertRaw('<h2>TEST COLOR PREVIEW</h2>');
  }

}
