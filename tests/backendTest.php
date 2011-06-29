<?php

/*
* @file
*  We choose to test the backend system in two parts.
*    - Origin. These tests assure that we are generate a proper ssh command
*        when a backend invoke is needed.
*    - Target. These tests assure that drush generates a delimited JSON array
*        when called with --backend option.
*
*  Advantages of this approach:
*    - No network calls and thus more robust.
*    - No network calls and thus faster.
*/

class backendCase extends Drush_CommandTestCase {
  const DRUSH_BACKEND_OUTPUT_DELIMITER = 'DRUSH_BACKEND_OUTPUT_START>>>%s<<<DRUSH_BACKEND_OUTPUT_END';

  /*
   * Covers the following origin responsibilities.
   *   - A remote host is recognized in site specification.
   *   - Generates expected ssh command.
   *
   * General handling of site aliases will be in sitealiasTest.php.
   */
  function testOrigin() {
    $exec = sprintf('%s %s version arg1 arg2 --simulate --ssh-options=%s 2>/dev/null | grep ssh', self::unish_escapeshellarg(UNISH_DRUSH), self::unish_escapeshellarg('user@server/path/to/drupal#sitename'), self::unish_escapeshellarg('-i mysite_dsa'));
    $this->execute($exec);
    // $expected might be different on non unix platforms. We shall see.
    $expected = "Simulating backend invoke: ssh -i mysite_dsa user@server 'drush  --simulate --uri=sitename --root=/path/to/drupal version arg1 arg2 2>&1' 2>&1";
    $output = $this->getOutput();
    $this->assertEquals($expected, $output, 'Expected ssh command was built');
  }

  /*
   * Covers the following target responsibilities.
   *   - Interpret stdin as options as per REST API.
   *   - Successfully execute specified command.
   *   - JSON object has expected contents (including errors).
   *   - JSON object is wrapped in expected delimiters.
  */
  function testTarget() {
    $stdin = json_encode(array('filter'=>'sql'));
    $exec = sprintf('echo %s | %s help --backend 2>/dev/null', self::unish_escapeshellarg($stdin), self::unish_escapeshellarg(UNISH_DRUSH));
    $this->execute($exec);
    $parsed = $this->parse($this->getOutput());
    $this->assertTrue((bool) $parsed, 'Successfully parsed backend output');
    $this->assertArrayHasKey('log', $parsed);
    $this->assertArrayHasKey('output', $parsed);
    $this->assertArrayHasKey('object', $parsed);
    $this->assertEquals(self::EXIT_SUCCESS, $parsed['error_status']);
    // This assertion shows that `help` was called and that stdin options were respected.
    $this->assertStringStartsWith('SQL commands', $parsed['output']);
    $this->assertEquals('Bootstrap to phase 0.', $parsed['log'][0]['message']);

    // Check error propogation by requesting an invalid command (missing Drupal site).
    $exec = sprintf('%s core-cron --backend 2>/dev/null', self::unish_escapeshellarg(UNISH_DRUSH));
    $this->execute($exec, self::EXIT_ERROR);
    $parsed = $this->parse($this->getOutput());
    $this->assertEquals(1, $parsed['error_status']);
    $this->assertArrayHasKey('DRUSH_NO_DRUPAL_ROOT', $parsed['error_log']);
  }

  /*
   * Covers the following target responsibilities.
   *   - Insures that the 'Drush version' line from drush status appears in the output.
   *   - Insures that the backend output start marker appears in the output (this is a backend command).
   *   - Insures that the drush output appears before the backend output start marker (output is displayed in 'real time' as it is produced).
   */
  function testRealtimeOutput() {
    $exec = sprintf('%s core-status --backend 2>&1', self::unish_escapeshellarg(UNISH_DRUSH));
    $this->execute($exec);

    $output = $this->getOutput();
    $drush_version_offset = strpos($output, "Drush version");
    $backend_output_offset = strpos($output, "DRUSH_BACKEND_OUTPUT_START>>>");

    $this->assertTrue($drush_version_offset !== FALSE, "'Drush version' string appears in output.");
    $this->assertTrue($backend_output_offset !== FALSE, "Drush backend output marker appears in output.");
    $this->assertTrue($drush_version_offset < $backend_output_offset, "Drush version string appears in output before the backend output marker.");
  }

  /*
   * A slightly less functional copy of drush_backend_parse_output().
   */
  function parse($string) {
    $regex = sprintf(self::DRUSH_BACKEND_OUTPUT_DELIMITER, '(.*)');
    preg_match("/$regex/s", $string, $match);
    if ($match[1]) {
      // we have our JSON encoded string
      $output = $match[1];
      // remove the match we just made and any non printing characters
      $string = trim(str_replace(sprintf(self::DRUSH_BACKEND_OUTPUT_DELIMITER, $match[1]), '', $string));
    }

    if ($output) {
      $data = json_decode($output, TRUE);
      if (is_array($data)) {
        return $data;
      }
    }
    return $string;
  }
}
