<?php

namespace Unish;

class backendUnitCase extends UnitUnishTestCase {

  /**
   * Covers the following target responsibilities.
   *   - Insures that drush_invoke_process() called with fork backend set is able
   *     to invoke a non-blocking process.
   */
  function testBackendFork() {
    // Ensure that file that will be created by forked process does not exist
    // before invocation.
    $test_file = UNISH_SANDBOX . '/fork_test.txt';
    if (file_exists($test_file)) {
      unlink($test_file);
    }

    // Sleep for a millisecond, then create the file
    $ev_php = "usleep(1000);fopen('$test_file','a');";
    drush_invoke_process("@none", "ev", array($ev_php), array(), array("fork" => TRUE));

    // Test file does not exist immediate after process forked
    $this->assertEquals(file_exists($test_file), FALSE);
    // Check every 100th of a second for up to 4 seconds to see if the file appeared
    $repetitions = 400;
    while (!file_exists($test_file) && ($repetitions > 0)) {
      usleep(10000);
    }
    // Assert that the file did finally appear
    $this->assertEquals(file_exists($test_file), TRUE);
  }
}
