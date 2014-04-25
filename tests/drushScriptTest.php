<?php

namespace Unish;

/**
 * Tests for the 'drush' script itself
 */
class drushScriptCase extends CommandUnishTestCase {

  /**
   * Test `PHP_OPTIONS=... drush`
   */
  public function testPhpOptionsTest() {
    // @todo: could probably run this test on mingw
    if ($this->is_windows()) {
      $this->markTestSkipped('Environment variable tests not currently functional on Windows.');
    }

    $options = array();
    $env = array('PHP_OPTIONS' => '-d default_mimetype="text/drush"');
    $this->drush('ev', array('print ini_get("default_mimetype");'), $options, NULL, NULL, self::EXIT_SUCCESS, NULL, $env);
    $output = $this->getOutput();
    $this->assertEquals('text/drush', $output);
  }
}
