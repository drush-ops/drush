<?php

namespace Unish;

/**
 *  We choose to test the backend system in two parts.
 *    - Origin. These tests assure that we generate a proper ssh command
 *        when a redispatch is needed. See RedispatchTest.php
 *    - Target. These tests assure that drush generates a delimited JSON array
 *        when called with --backend option (legacy backend invoke).
 *
 *  Advantages of this approach:
 *    - No network calls and thus more robust.
 *    - No network calls and thus faster.
 *
 *  @group base
 */
class BackendCase extends CommandUnishTestCase
{
    /**
     * Covers the following target responsibilities.
     *   - Interpret stdin as options as per REST API.
     *   - Successfully execute specified command.
     *   - JSON object has expected contents (including errors).
     *   - JSON object is wrapped in expected delimiters.
     */
    public function testTarget()
    {
        // Backend invoke always runs in non-strict mode now.
        $stdin = json_encode([]);
        $exec = sprintf('%s version --not-exist --backend', self::getDrush());
        $this->execute($exec, self::EXIT_SUCCESS, null, null, $stdin);
        $exec = sprintf('%s version --backend', self::getDrush());
        $this->execute($exec, self::EXIT_SUCCESS, null, null, $stdin);
        $parsed = $this->parseBackendOutput($this->getOutput());
        $this->assertTrue((bool) $parsed, 'Successfully parsed backend output');
        $this->assertArrayHasKey('log', $parsed);
        $this->assertArrayHasKey('output', $parsed);
        $this->assertArrayHasKey('object', $parsed);
        $this->assertEquals(self::EXIT_SUCCESS, $parsed['error_status']);
        // This assertion shows that `version` was called and that stdin options were respected.
        $this->assertEquals('drush-version', key($parsed['object']));
        // @todo --backend not currently populating 'output' for Annotated commands.
        // $this->assertStringStartsWith(' Drush Version ', $parsed['output']);
        $this->assertEquals('Starting bootstrap to none', $parsed['log'][0]['message']);
    }

    public function testBackendErrorStatus()
    {
        $this->markTestSkipped('TODO: @none should prevent selection of site at cwd');
        // Check error propagation by requesting an invalid command (missing Drupal site).
        $this->drush('core-cron', [], ['backend' => null], '@none', null, self::EXIT_ERROR);
        $parsed = $this->parseBackendOutput($this->getOutput());
        $this->assertEquals(1, $parsed['error_status']);
    }

    /**
     * @deprecated Covers old backend invoke system.
     *
     * Covers the following target responsibilities.
     *   - Insures that the 'Drush version' line from drush status appears in the output.
     *   - Insures that the backend output start marker appears in the output (this is a backend command).
     *   - Insures that the drush output appears before the backend output start marker (output is displayed in 'real time' as it is produced).
     */
    public function testRealtimeOutput()
    {
        $exec = sprintf('%s core-status --backend --format=yaml 2>&1', self::getDrush());
        $this->execute($exec);

        $output = $this->getOutput();
        $drush_version_offset = strpos($output, "drush-version");
        $backend_output_offset = strpos($output, "DRUSH_BACKEND_OUTPUT_START>>>");

        $this->assertTrue($drush_version_offset !== false, "'Drush version' string appears in output.");
        $this->assertTrue($backend_output_offset !== false, "Drush backend output marker appears in output.");
        $this->assertTrue($drush_version_offset < $backend_output_offset, "Drush version string appears in output before the backend output marker.");
    }

    /**
     * Covers the following target responsibilities.
     *   - Insures that function result is returned in --backend mode
     */
    public function testBackendFunctionResult()
    {
        $php = "return 'bar'";
        $this->drush('php-eval', [$php], ['backend' => null]);
        $parsed = $this->parseBackendOutput($this->getOutput());
        // assert that $parsed has 'bar'
        $this->assertEquals("'bar'", var_export($parsed['object'], true));
    }

    /**
     * Covers the following target responsibilities.
     *   - Insures that backend_set_result is returned in --backend mode
     *   - Insures that the result code for the function does not overwrite
     *     the explicitly-set value
     */
    public function testBackendSetResult()
    {
        $php = "drush_backend_set_result('foo'); return 'bar'";
        $this->drush('php-eval', [$php], ['backend' => null]);
        $parsed = $this->parseBackendOutput($this->getOutput());
        // assert that $parsed has 'foo' and not 'bar'
        $this->assertEquals("'foo'", var_export($parsed['object'], true));
    }

    /**
     * Covers the following target responsibilities.
     *   - Insures that the backend option 'invoke-multiple' will cause multiple commands to be executed.
     *   - Insures that the right number of commands run.
     *   - Insures that the 'concurrent'-format result array is returned.
     *   - Insures that correct results are returned from each command.
     */
    public function testBackendInvokeMultiple()
    {
        $this->markTestIncomplete('Depends on concurrency');
        $options = [
        'backend' => null,
        'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
        ];
        $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y'), array('invoke-multiple' => '3')); return \$values;";
        $this->drush('php-eval', [$php], $options);
        $parsed = $this->parseBackendOutput($this->getOutput());
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
)", var_export($values['object'], true));
        }
    }
    /**
     * Covers the following target responsibilities.
     *   - Insures that arrays are stripped when using --backend mode's method GET
     *   - Insures that arrays can be returned as the function result of
     *     backend invoke.
     */
    public function testBackendMethodGet()
    {
        $options = [
        'backend' => null,
        'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
        ];
        $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'data' => array('a' => 1, 'b' => 2)), array('method' => 'GET')); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
        $this->drush('php-eval', [$php], $options);
        $parsed = $this->parseBackendOutput($this->getOutput());
        // assert that $parsed has value of 'x'
        $this->assertContains("'x' => 'y'", var_export($parsed['object'], true));
    }

    /**
     * Covers the following target responsibilities.
     *   - Insures that complex arrays can be passed through when using --backend mode's method POST
     *   - Insures that arrays can be returned as the function result of
     *     backend invoke.
     */
    public function testBackendMethodPost()
    {
        $options = [
        'backend' => null,
        'include' => dirname(__FILE__), // Find unit.drush.inc commandfile.
        ];
        $php = "\$values = drush_invoke_process('@none', 'unit-return-options', array('value'), array('x' => 'y', 'data' => array('a' => 1, 'b' => 2)), array('method' => 'POST')); return array_key_exists('object', \$values) ? \$values['object'] : 'no result';";
        $this->drush('php-eval', [$php], $options);
        $parsed = $this->parseBackendOutput($this->getOutput());
        // assert that $parsed has 'x' and 'data'
        $this->assertEquals('y', $parsed['object']['x']);
        $this->assertEquals([
        'a' => 1,
        'b' => 2,
        ], $parsed['object']['data']);
    }
}
