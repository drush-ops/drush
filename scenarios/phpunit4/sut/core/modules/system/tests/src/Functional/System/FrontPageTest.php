<?php

namespace Drupal\Tests\system\Functional\System;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests front page functionality and administration.
 *
 * @group system
 */
class FrontPageTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'system_test', 'views'];

  /**
   * The path to a node that is created for testing.
   *
   * @var string
   */
  protected $nodePath;

  protected function setUp() {
    parent::setUp();

    // Create admin user, log in admin user, and create one node.
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'administer site configuration',
    ]));
    $this->drupalCreateContentType(['type' => 'page']);
    $this->nodePath = "node/" . $this->drupalCreateNode(['promote' => 1])->id();

    // Configure 'node' as front page.
    $this->config('system.site')->set('page.front', '/node')->save();
    // Enable front page logging in system_test.module.
    \Drupal::state()->set('system_test.front_page_output', 1);
  }

  /**
   * Test front page functionality.
   */
  public function testDrupalFrontPage() {
    // Create a promoted node to test the <title> tag on the front page view.
    $settings = [
      'title' => $this->randomMachineName(8),
      'promote' => 1,
    ];
    $this->drupalCreateNode($settings);
    $this->drupalGet('');
    $this->assertTitle('Home | Drupal');

    $this->assertText(t('On front page.'), 'Path is the front page.');
    $this->drupalGet('node');
    $this->assertText(t('On front page.'), 'Path is the front page.');
    $this->drupalGet($this->nodePath);
    $this->assertNoText(t('On front page.'), 'Path is not the front page.');

    // Change the front page to an invalid path.
    $edit = ['site_frontpage' => '/kittens'];
    $this->drupalPostForm('admin/config/system/site-information', $edit, t('Save configuration'));
    $this->assertText(t("Either the path '@path' is invalid or you do not have access to it.", ['@path' => $edit['site_frontpage']]));

    // Change the front page to a path without a starting slash.
    $edit = ['site_frontpage' => $this->nodePath];
    $this->drupalPostForm('admin/config/system/site-information', $edit, t('Save configuration'));
    $this->assertRaw(new FormattableMarkup("The path '%path' has to start with a slash.", ['%path' => $edit['site_frontpage']]));

    // Change the front page to a valid path.
    $edit['site_frontpage'] = '/' . $this->nodePath;
    $this->drupalPostForm('admin/config/system/site-information', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'), 'The front page path has been saved.');

    $this->drupalGet('');
    $this->assertText(t('On front page.'), 'Path is the front page.');
    $this->drupalGet('node');
    $this->assertNoText(t('On front page.'), 'Path is not the front page.');
    $this->drupalGet($this->nodePath);
    $this->assertText(t('On front page.'), 'Path is the front page.');
  }

}
