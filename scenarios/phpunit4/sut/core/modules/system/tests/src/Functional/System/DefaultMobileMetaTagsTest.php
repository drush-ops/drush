<?php

namespace Drupal\Tests\system\Functional\System;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Confirm that the default mobile meta tags appear as expected.
 *
 * @group system
 */
class DefaultMobileMetaTagsTest extends BrowserTestBase {

  /**
   * Array of default meta tags to insert into the page.
   *
   * @var array
   */
  protected $defaultMetaTags;

  protected function setUp() {
    parent::setUp();
    $this->defaultMetaTags = [
      'viewport' => '<meta name="viewport" content="width=device-width, initial-scale=1.0" />',
    ];
  }

  /**
   * Verifies that the default mobile meta tags are added.
   */
  public function testDefaultMetaTagsExist() {
    $this->drupalGet('');
    foreach ($this->defaultMetaTags as $name => $metatag) {
      $this->assertRaw($metatag, new FormattableMarkup('Default Mobile meta tag "@name" displayed properly.', ['@name' => $name]), 'System');
    }
  }

  /**
   * Verifies that the default mobile meta tags can be removed.
   */
  public function testRemovingDefaultMetaTags() {
    \Drupal::service('module_installer')->install(['system_module_test']);
    $this->drupalGet('');
    foreach ($this->defaultMetaTags as $name => $metatag) {
      $this->assertNoRaw($metatag, new FormattableMarkup('Default Mobile meta tag "@name" removed properly.', ['@name' => $name]), 'System');
    }
  }

}
