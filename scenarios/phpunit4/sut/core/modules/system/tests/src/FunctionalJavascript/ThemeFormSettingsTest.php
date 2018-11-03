<?php

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests that theme form settings works correctly.
 *
 * @group system
 */
class ThemeFormSettingsTest extends WebDriverTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['file'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $admin = $this->drupalCreateUser(['administer themes']);
    $this->drupalLogin($admin);
  }

  /**
   * Tests that submission handler works correctly.
   *
   * @dataProvider providerTestFormSettingsSubmissionHandler
   */
  public function testFormSettingsSubmissionHandler($theme) {

    \Drupal::service('theme_handler')->install([$theme]);

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet("admin/appearance/settings/$theme");

    // Add a new managed file.
    $file = current($this->getTestFiles('image'));
    $image_file_path = \Drupal::service('file_system')->realpath($file->uri);
    $page->attachFileToField('files[custom_logo]', $image_file_path);
    $assert_session->waitForButton('custom_logo_remove_button');

    // Assert the new file is uploaded as temporary. This file should not be
    // saved as permanent if settings are not submited.
    $image_field = $this->xpath('//input[@name="custom_logo[fids]"]')[0];
    $file = File::load($image_field->getValue());
    $this->assertFalse($file->isPermanent());

    $page->pressButton('Save configuration');
    \Drupal::entityTypeManager()->getStorage('file')->resetCache();

    // Assert the uploaded file is saved as permanent.
    $image_field = $this->xpath('//input[@name="custom_logo[fids]"]')[0];
    $file = File::load($image_field->getValue());
    $this->assertTrue($file->isPermanent());
  }

  /**
   * Provides test data for ::testFormSettingsSubmissionHandler().
   */
  public function providerTestFormSettingsSubmissionHandler() {
    return [
      'test theme.theme' => ['test_theme_theme'],
      'test theme-settings.php' => ['test_theme_settings'],
    ];
  }

}
