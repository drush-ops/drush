<?php

namespace Unish;

/**
 *  We choose to test the backend system in two parts.
 *    - Origin. These tests assure that we are generate a proper ssh command
 *        when a backend invoke is needed.
 *    - Target. These tests assure that drush generates a delimited JSON array
 *        when called with --backend option.
 *
 *  Advantages of this approach:
 *    - No network calls and thus more robust.
 *    - No network calls and thus faster.
 *
 *  @group base
 */
class backendCase extends CommandUnishTestCase {
  // Test to insure that calling drush_invoke_process() with 'dispatch-using-alias'
  // will build a command string that uses the alias instead of --root and --uri.
  function testDispatchUsingAlias() {
    $this->markTestIncomplete('Started failing due to https://github.com/drush-ops/drush/pull/555');

    $aliasPath = UNISH_SANDBOX . '/aliases';
    mkdir($aliasPath);
    $aliasFile = $aliasPath . '/foo.aliases.drushrc.php';
    $aliasContents = <<<EOD
  <?php
  // Written by Unish. This file is safe to delete.
  \$aliases['dev'] = array('root' => '/fake/path/to/root', 'uri' => 'default');
EOD;
    file_put_contents($aliasFile, $aliasContents);
    $options = array(
      'alias-path' => $aliasPath,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
      'script-path' => dirname(__FILE__) . '/resources', // Find unit.drush.inc commandfile.
      'backend' => TRUE,
    );
    $this->drush('php-script', array('testDispatchUsingAlias_script'), $options);
    $parsed = $this->parse_backend_output($this->getOutput());

    // $parsed['with'] and $parsed['without'] now contain an array
    // each with the original arguments passed in with and without
    // 'dispatch-using-alias', respectively.
    $argDifference = array_diff($parsed['object']['with'], $parsed['object']['without']);
    $this->assertEquals(array_diff(array_values($argDifference), array('@foo.dev')), array());
    $argDifference = array_diff($parsed['object']['without'], $parsed['object']['with']);
    $this->assertEquals(array_diff(array_values($argDifference), array('--root=/fake/path/to/root', '--uri=default')), array());
  }

  /**
   * Covers the following origin responsibilities.
   *   - A remote host is recognized in site specification.
   *   - Generates expected ssh command.
   *
   * General handling of site aliases will be in sitealiasTest.php.
   */
  function testOrigin() {
    $site_specification = 'user@server/path/to/drupal#sitename';
    $exec = sprintf('%s %s version arg1 arg2 --simulate --ssh-options=%s 2>%s', UNISH_DRUSH, self::escapeshellarg($site_specification), self::escapeshellarg('-i mysite_dsa'), self::escapeshellarg($this->bit_bucket()));
    $this->execute($exec);
    $bash = $this->escapeshellarg('drush  --uri=sitename --root=/path/to/drupal  version arg1 arg2 2>&1');
    $expected = "Simulating backend invoke: ssh -i mysite_dsa user@server $bash 2>&1";
    $output = $this->getOutput();
    $this->assertContains($expected, $output, 'Expected ssh command was built');

    // Assure that arguments and options are passed along to a command thats not recognized locally.
    $this->drush('non-existent-command', array('foo'), array('bar' => 'baz', 'simulate' => NULL), $site_specification);
    $output = $this->getOutput();
    $this->assertContains('foo', $output);
    $this->assertContains('--bar=baz', $output);
  }

  /**
   * Covers the following target responsibilities.
   *   - Interpret stdin as options as per REST API.
   *   - Successfully execute specified command.
   *   - JSON object has expected contents (including errors).
   *   - JSON object is wrapped in expected delimiters.
  */
  function testTarget() {
    $stdin = json_encode(array('filter'=>'sql'));
    $exec = sprintf('%s version --backend 2>%s', UNISH_DRUSH, self::escapeshellarg($this->bit_bucket()));
    $this->execute($exec, self::EXIT_SUCCESS, NULL, NULL, $stdin);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertTrue((bool) $parsed, 'Successfully parsed backend output');
    $this->assertArrayHasKey('log', $parsed);
    $this->assertArrayHasKey('output', $parsed);
    $this->assertArrayHasKey('object', $parsed);
    $this->assertEquals(self::EXIT_SUCCESS, $parsed['error_status']);
    // This assertion shows that `version` was called and that stdin options were respected.
    $this->assertStringStartsWith(' Drush Version ', $parsed['output']);
    $this->assertEquals('Starting Drush preflight.', $parsed['log'][1]['message']);

    // Check error propogation by requesting an invalid command (missing Drupal site).
    $this->drush('core-cron', array(), array('backend' => NULL), NULL, NULL, self::EXIT_ERROR);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertEquals(1, $parsed['error_status']);
    $this->assertArrayHasKey('DRUSH_COMMAND_INSUFFICIENT_BOOTSTRAP', $parsed['error_log']);
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that the 'Drush version' line from drush status appears in the output.
   *   - Insures that the backend output start marker appears in the output (this is a backend command).
   *   - Insures that the drush output appears before the backend output start marker (output is displayed in 'real time' as it is produced).
   */
  function testRealtimeOutput() {
    $exec = sprintf('%s core-status --backend --nocolor 2>&1', UNISH_DRUSH);
    $this->execute($exec);

    $output = $this->getOutput();
    $drush_version_offset = strpos($output, "Drush version");
    $backend_output_offset = strpos($output, "DRUSH_BACKEND_OUTPUT_START>>>");

    $this->assertTrue($drush_version_offset !== FALSE, "'Drush version' string appears in output.");
    $this->assertTrue($backend_output_offset !== FALSE, "Drush backend output marker appears in output.");
    $this->assertTrue($drush_version_offset < $backend_output_offset, "Drush version string appears in output before the backend output marker.");
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that function result is returned in --backend mode
   */
  function testBackendFunctionResult() {
    $php = "return 'bar'";
    $this->drush('php-eval', array($php), array('backend' => NULL));
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has 'bar'
    $this->assertEquals("'bar'", var_export($parsed['object'], TRUE));
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that backend_set_result is returned in --backend mode
   *   - Insures that the result code for the function does not overwrite
   *     the explicitly-set value
   */
  function testBackendSetResult() {
    $php = "drush_backend_set_result('foo'); return 'bar'";
    $this->drush('php-eval', array($php), array('backend' => NULL));
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has 'foo' and not 'bar'
    $this->assertEquals("'foo'", var_export($parsed['object'], TRUE));
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that the backend option 'invoke-multiple' will cause multiple commands to be executed.
   *   - Insures that the right number of commands run.
   *   - Insures that the 'concurrent'-format result array is returned.
   *   - Insures that correct results are returned from each command.
   */
  function testBackendInvokeMultiple() {
    $options = array(
      'backend' => NULL,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'strict' => 0), array('invoke-multiple' => '3')); return \$values;";
    $this->drush('php-eval', array($php), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has a 'concurrent'-format output result
    $this->assertEquals('concurrent', implode(',', array_keys($parsed['object'])));
    // assert that the concurrent output has indexes 0, 1 and 2 (in any order)
    $concurrent_indexes = array_keys($parsed['object']['concurrent']);
    sort($concurrent_indexes);
    $this->assertEquals('0,1,2', implode(',', $concurrent_indexes));
    foreach ($parsed['object']['concurrent'] as $index => $values) {
      // assert that each result contains 'x' => 'y' and nothing else
      $this->assertEquals("array (
  'x' => 'y',
)", var_export($values['object'], TRUE));
    }
  }
  /**
   * Covers the following target responsibilities.
   *   - Insures that arrays are stripped when using --backend mode's method GET
   *   - Insures that arrays can be returned as the function result of
   *     backend invoke.
   */
  function testBackendMethodGet() {
    $options = array(
      'backend' => NULL,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'strict' => 0, 'data' => array('a' => 1, 'b' => 2)), array('method' => 'GET')); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
    $this->drush('php-eval', array($php), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has 'x' but not 'data'
    $this->assertEquals("array (
  'x' => 'y',
)", var_export($parsed['object'], TRUE));
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that complex arrays can be passed through when using --backend mode's method POST
   *   - Insures that arrays can be returned as the function result of
   *     backend invoke.
   */
  function testBackendMethodPost() {
    $options = array(
      'backend' => NULL,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'strict' => 0, 'data' => array('a' => 1, 'b' => 2)), array('method' => 'POST')); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
    $this->drush('php-eval', array($php), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has 'x' and 'data'
    $this->assertEquals(array (
  'x' => 'y',
  'data' =>
  array (
    'a' => 1,
    'b' => 2,
  ),
), $parsed['object']);
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that backend invoke can properly re-assemble packets
   *     that are split across process-read-size boundaries.
   *
   * This test works by repeating testBackendMethodGet(), while setting
   * '#process-read-size' to a very small value, insuring that packets
   * will be split.
   */
  function testBackendReassembleSplitPackets() {
    $options = array(
      'backend' => NULL,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $min = 1;
    $max = 4;
    $read_sizes_to_test = array(4096);
    if (in_array('--debug', $_SERVER['argv'])) {
      $read_sizes_to_test[] = 128;
      $read_sizes_to_test[] = 16;
      $max = 16;
    }
    foreach ($read_sizes_to_test as $read_size) {
      $log_message="";
      for ($i = $min; $i <= $max; $i++) {
        $log_message .= "X";
        $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('log-message' => '$log_message', 'x' => 'y$read_size', 'strict' => 0, 'data' => array('a' => 1, 'b' => 2)), array('method' => 'GET', '#process-read-size' => $read_size)); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
        $this->drush('php-eval', array($php), $options);
        $parsed = $this->parse_backend_output($this->getOutput());
        // assert that $parsed has 'x' but not 'data'
        $all_warnings=array();
        foreach ($parsed['log'] as $log) {
          if ($log['type'] == 'warning') {
            $all_warnings[] = $log['message'];
          }
        }
        $this->assertEquals("$log_message,done", implode(',', $all_warnings), 'Log reconstruction with read_size ' . $read_size);
        $this->assertEquals("array (
  'x' => 'y$read_size',
)", var_export($parsed['object'], TRUE));
      }
    }
  }
}
