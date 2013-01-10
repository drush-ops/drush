<?php

/*
 * @file
 *   Tests for the 'drush' script itself
 */
class drushScriptCase extends Drush_CommandTestCase {

  /*
   * Test `PHP_OPTIONS=... drush`
   */
  public function testPhpOptionsTest() {
    // todo: could probably run this test on mingw
    if ($this->is_windows()) {
      $this->markTestSkipped('environment variable tests not currently functional on Windows.');
    }

    $options = array(
    );
    $env = array(
      'PHP_OPTIONS' => '-d default_mimetype="text/drush"',
    );
    $this->drush('ev', array('print ini_get("default_mimetype");'), $options, NULL, NULL, self::EXIT_SUCCESS, NULL, $env);
    $output = $this->getOutput();
    $this->assertEquals('text/drush', $output);
  }
}
