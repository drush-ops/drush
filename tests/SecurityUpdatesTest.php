<?php

namespace Unish;

/**
 * Tests "pm:security" commands for D8+.
 * @group commands
 * @group slow
 * @group pm
 */
class SecurityUpdatesTest extends CommandUnishTestCase {

  /**
   * Test that insecure packages are correctly identified.
   */
  function testInsecurePackage() {
    $this->execute("composer config repositories.0 '{\"type\": \"composer\", \"url\": \"https://packages.drupal.org/8\"}'", self::EXIT_SUCCESS, $this->webRoot() . '../');
    $this->timeout = 100;
    $this->idleTimeout = 100;
    $this->execute("composer require drupal/alinks:1.0.0 --no-interaction --no-ansi --no-suggest --prefer-dist", self::EXIT_SUCCESS, $this->webRoot() . '../');
    $this->drush('pm:security');
    $this->assertContains('One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.', $this->getOutput());
    $this->assertContains('Try running: composer require drupal/alinks:^1.1 --update-with-dependencies', $this->getOutput());
    $this->assertContains('drupal/alinks', $this->getOutput());
    $this->assertContains('1.0.0', $this->getOutput());
    $this->assertContains('1.1', $this->getOutput());
  }
}
