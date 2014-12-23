<?php

/**
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
    $this->drush('watchdog-show', array(), $options + array('extended' => NULL));
    $output = $this->getOutput();
    $this->assertGreaterThanOrEqual($message_chars, substr_count($output, $char));

    // Test multiple severity levels support.
    $warnings = 5;
    $errors = 3;
    for ($i = 0; $i < $warnings; $i++) {
      $this->drush('php-eval', array("watchdog('drush', '" . uniqid('drush_') . "', array(), WATCHDOG_WARNING)"), $options);
    }
    for ($i = 0; $i < $errors; $i++) {
      $this->drush('php-eval', array("watchdog('drush', '" . uniqid('drush_') . "', array(), WATCHDOG_ERROR)"), $options);
    }
    $this->drush('watchdog-show', array(), $options + array('severity' => 'info', 'field-labels' => 0));
    $output = array_filter($this->getOutputAsList());
    $this->assertEquals(2, count($output));
    $this->drush('watchdog-show', array(), $options + array('severity' => 'warning,error', 'field-labels' => 0));
    $output = array_filter($this->getOutputAsList());
    $this->assertEquals($warnings + $errors, count($output));
    $this->drush('watchdog-show', array(), $options + array('severity' => 'warning,info,doesntexist', 'field-labels' => 0), NULL, NULL, self::EXIT_ERROR);
    $output = array_filter($this->getOutputAsList());
    $this->assertEmpty($output);
    $this->drush('watchdog-show', array(), $options + array('severity' => '~warning,~notice', 'field-labels' => 0));
    $output = array_filter($this->getOutputAsList());
    // 2 WATCHDOG_INFO + WATCHDOG_ERRORS.
    $this->assertEquals(2 + $errors, count($output));
    $this->drush('watchdog-show', array(), $options + array('severity' => '-error,~notice', 'field-labels' => 0));
    $output = array_filter($this->getOutputAsList());
    // 2 WATCHDOG_INFO + WATCHDOG_ERRORS.
    $this->assertEquals(2 + $warnings, count($output));

    // Tests message deletion.
    $this->drush('watchdog-delete', array('all'), $options);
    $output = $this->getOutput();
    $this->drush('watchdog-show', array(), $options);
    $output = $this->getOutput();
    $this->assertEmpty($output);
  }
}
