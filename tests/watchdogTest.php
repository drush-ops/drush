<?php

/*
 * @file
 *   Tests watchdog-show and watchdog-delete commands
 *
 * @group commands
 */
class WatchdogCase extends Drush_CommandTestCase {

  function testWatchdog() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    // Enable dblog module and verify that the watchdog messages are listed
    $this->drush('pm-enable', array('dblog'), $options);
    $this->drush('watchdog-show', array(), $options);
    $output = $this->getOutput();
    $this->assertContains('dblog module installed.', $output);
    $this->assertContains('dblog module enabled.', $output);

    // Add a new entry with a long message with the letter 'd' and verify that watchdog-show does
    // not print it completely in the listing unless --full is given.
    // As the output is formatted so lines may be splitted, assertContains does not work
    // in this scenario. Therefore, we will count the number of times a character is present.
    $message_chars = 300;
    $char = '*';
    $message = str_repeat($char, $message_chars);
    $this->drush('php-eval', array("watchdog('drush', '" . $message . "')"), $options);
    $this->drush('watchdog-show', array(), $options);
    $output = $this->getOutput();
    $this->assertGreaterThan(substr_count($output, $char), $message_chars);
    $this->drush('watchdog-show', array(), $options + array('full' => NULL));
    $output = $this->getOutput();
    $this->assertGreaterThanOrEqual($message_chars, substr_count($output, $char));

    // Tests message deletion
    $this->drush('watchdog-delete', array('all'), $options);
    $output = $this->getOutput();
    $this->drush('watchdog-show', array(), $options);
    $output = $this->getOutput();
    $this->assertEmpty($output);
  }
}
