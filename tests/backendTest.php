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
  function testDispatchUsingAlias()
  {
    $unishAliases = [
      'remote' => [
        'host' => 'server.isp.com',
        'user' => 'www-admin',
        'root' => '/path/to/drupal',
        'uri' => 'http://example.com',
        'paths' => [
          'drush-script' => '/usr/local/bin/drush',
        ],
      ],
    ];
    // n.b. writeUnishConfig will overwrite the alias files create by setupDrupal
    $this->writeUnishConfig($unishAliases);
    $this->drush('status', [], ['simulate' => NULL], '@unish.remote');
    $output = $this->getOutput();

    // Clean up -- our other tests do not want extra configuration
    unlink(self::getSandbox() . '/etc/drush/drush.yml');

    $output = preg_replace('#  *#', ' ', $output);
    $output = preg_replace('# -t #', ' ', $output); // volkswagon away the -t, it's not relevant to what we're testing here
    $output = preg_replace('#' . self::getSandbox() . '#', '__SANDBOX__', $output);
    $this->assertContains("Simulating backend invoke: ssh -o PasswordAuthentication=no www-admin@server.isp.com '/usr/local/bin/drush --alias-path=__SANDBOX__/etc/drush --root=/path/to/drupal --uri=http://example.com --no-ansi status", $output);
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
    $exec = sprintf('%s %s version --simulate --ssh-options=%s 2>%s', self::getDrush(), self::escapeshellarg($site_specification), self::escapeshellarg('-i mysite_dsa'), self::escapeshellarg($this->bit_bucket()));
    $this->execute($exec);
    $bash = $this->escapeshellarg('drush --root=/path/to/drupal --uri=sitename version 2>&1');
    $expected = "Simulating backend invoke: ssh -i mysite_dsa user@server $bash 2>&1";
    $output = $this->getOutput();
    // We do not care if Drush inserts a -t or not in the string. Depends on whether there is a tty.
    $output = preg_replace('# -t #', ' ', $output);
    // Remove double spaces from output to help protect test from false negatives if spacing changes subtlely
    $output = preg_replace('#  *#', ' ', $output);
    $this->assertContains($expected, $output, 'Expected ssh command was built');
  }

  function testNonExistentCommand()
  {
    $this->markTestSkipped('Cannot run remote commands that do not exist locally');
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
    // Backend invoke always runs in non-strict mode now.
    $stdin = json_encode([]);
    $exec = sprintf('%s version --not-exist --backend', self::getDrush());
    $this->execute($exec, self::EXIT_SUCCESS, NULL, NULL, $stdin);
    $exec = sprintf('%s version --backend', self::getDrush());
    $this->execute($exec, self::EXIT_SUCCESS, NULL, NULL, $stdin);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertTrue((bool) $parsed, 'Successfully parsed backend output');
    $this->assertArrayHasKey('log', $parsed);
    $this->assertArrayHasKey('output', $parsed);
    $this->assertArrayHasKey('object', $parsed);
    $this->assertEquals(self::EXIT_SUCCESS, $parsed['error_status']);
    // This assertion shows that `version` was called and that stdin options were respected.
    $this->assertEquals('drush-version', key($parsed['object']));
    // @todo --backend not currently populating 'output' for Annotated commands.
    // $this->assertStringStartsWith(' Drush Version ', $parsed['output']);
    $this->assertEquals('Bootstrap to none', $parsed['log'][0]['message']);
  }

  function testBackendErrorStatus() {
    // Check error propogation by requesting an invalid command (missing Drupal site).
    $this->drush('core-cron', array(), array('backend' => NULL), NULL, NULL, self::EXIT_ERROR);
    $parsed = $this->parse_backend_output($this->getOutput());
    $this->assertEquals(1, $parsed['error_status']);
  }

  /**
   * Covers the following target responsibilities.
   *   - Insures that the 'Drush version' line from drush status appears in the output.
   *   - Insures that the backend output start marker appears in the output (this is a backend command).
   *   - Insures that the drush output appears before the backend output start marker (output is displayed in 'real time' as it is produced).
   */
  function testRealtimeOutput() {
    $exec = sprintf('%s core-status --backend --format=yaml --no-ansi 2>&1', self::getDrush());
    $this->execute($exec);

    $output = $this->getOutput();
    $drush_version_offset = strpos($output, "drush-version");
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
    $this->markTestIncomplete('Depends on concurrency');
    $options = array(
      'backend' => NULL,
      'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
    );
    $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y'), array('invoke-multiple' => '3')); return \$values;";
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
    $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'data' => array('a' => 1, 'b' => 2)), array('method' => 'GET')); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
    $this->drush('php-eval', array($php), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has value of 'x'
    $this->assertContains("'x' => 'y'", var_export($parsed['object'], TRUE));
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
    $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'data' => array('a' => 1, 'b' => 2)), array('method' => 'POST')); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
    $this->drush('php-eval', array($php), $options);
    $parsed = $this->parse_backend_output($this->getOutput());
    // assert that $parsed has 'x' and 'data'
    $this->assertEquals('y', $parsed['object']['x']);
    $this->assertEquals(array (
    'a' => 1,
    'b' => 2,
), $parsed['object']['data']);
  }
}
