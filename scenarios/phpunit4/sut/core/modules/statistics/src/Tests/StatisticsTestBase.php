<?php

namespace Drupal\statistics\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Defines a base class for testing the Statistics module.
 *
 * @deprecated Scheduled for removal in Drupal 9.0.0.
 *   Use \Drupal\Tests\statistics\Functional\StatisticsTestBase instead.
 */
abstract class StatisticsTestBase extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'block', 'ban', 'statistics'];

  /**
   * User with permissions to ban IP's.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $blockingUser;

  protected function setUp() {
    parent::setUp();

    // Create Basic page node type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    }

    // Create user.
    $this->blockingUser = $this->drupalCreateUser([
      'access administration pages',
      'access site reports',
      'ban IP addresses',
      'administer blocks',
      'administer statistics',
      'administer users',
    ]);
    $this->drupalLogin($this->blockingUser);

    // Enable logging.
    $this->config('statistics.settings')
      ->set('count_content_views', 1)
      ->save();
  }

}
