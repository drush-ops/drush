<?php

namespace Unish;

/**
 * @group commands
 */
class WatchdogCase extends CommandUnishTestCase {

  function  testWatchdog() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    if (UNISH_DRUPAL_MAJOR_VERSION >= 7) {
      $this->drush('pm-enable', array('dblog'), $options);
    }
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $eval1 = "\\Drupal::logger('drush')->notice('Unish rocks.');";
    }
    else {
      $eval1 = "watchdog('drush', 'Unish rocks.');";
    }
    $this->drush('php-eval', array($eval1), $options);
    $this->drush('watchdog-show', array(), $options + array('count' => 50));
    $output = $this->getOutput();
    $this->assertContains('Unish rocks.', $output);

    // Add a new entry with a long message with the letter 'd' and verify that watchdog-show does
    // not print it completely in the listing unless --full is given.
    // As the output is formatted so lines may be splitted, assertContains does not work
    // in this scenario. Therefore, we will count the number of times a character is present.
    $message_chars = 300;
    $char = '*';
    $message = str_repeat($char, $message_chars);
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $eval2 = "\\Drupal::logger('drush')->notice('$message');";
    }
    else {
      $eval2 = "watchdog('drush', '$message');";
    }
    $this->drush('php-eval', array($eval2), $options);
    $this->drush('watchdog-show', array(), $options);
    $output = $this->getOutput();
    $this->assertGreaterThan(substr_count($output, $char), $message_chars);
    $this->drush('watchdog-show', array(), $options + array('extended' => NULL));
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
