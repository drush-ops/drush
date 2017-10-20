<?php

namespace Unish;

/**
 * Tests "pm:security" commands for D8+.
 * @group commands
 * @group slow
 * @group pm
 */
class SecurityUpdatesTest extends CommandUnishTestCase {

  function testInsecurePackage() {
    $this->execute("cat composer.json",self::EXIT_SUCCESS, $this->webRoot() . '../');
    $this->execute("composer require drupal/alinks:1.0.0", self::EXIT_SUCCESS, $this->webRoot() . '../');
    $this->drush('pm:security');
    $this->assertContains('One or more of your dependencies has an outstanding security update. Please apply update(s) immediately.', $this->getOutput());
    $this->assertContains('Try running: composer require drupal/alinks:^1.1 --update-with-dependencies', $this->getOutput());
    $this->assertContains('drupal/alinks', $this->getOutput());
    $this->assertContains('1.0.0', $this->getOutput());
    $this->assertContains('1.1', $this->getOutput());
  }
}
