<?php

/**
 * @file
 * Tests for rest-api commands.
 *
 * @group commands
 */

class RestApiTest extends Drush_CommandTestCase {
  /**
   * Test the rest-api-request command.
   */
  public function testRestApiRequest() {
    // Check an empty request to rest-api-request.
    $this->drush('rest-api-request');
    $output = $this->getOutput();
    $this->assertJson($output, 'Received valid JSON');
    // Check the contents of the json.
    $expected_error = '{"error_status":1,"error_log":"Invalid request. REST API requests must use this format: {@alias}\/{command}\/{argument}\/{argument_two}?{option=value\u0026option2=value2}"}';
    $this->assertJsonStringEqualsJsonString($expected_error, $output, 'Received expected JSON');
    // Now check a valid request, '@none/core-status?format=json'.
    $this->drush('rest-api-request', array('@none/core-status?format=json'));
    $output = $this->getOutput();
    $this->assertJson($output, 'Received valid JSON');
    $json = drush_json_decode($output);
    $this->assertEquals(0, $json['error_status'], 'Successful request.');
    $this->assertEmpty($json['error_log'], 'Error log is empty.');
    $this->assertJson($json['output'], 'Returned JSON data as requested.');
    // Make an invalid request, using a bogus alias.
    $this->drush('rest-api-request', array('@blah/core-status'));
    $output = $this->getOutput();
    $this->assertJsonStringEqualsJsonString($expected_error, $output, 'Received error message.');
    // Check for access denied on invalid host.
    $this->drush('rest-api-request', array('@none/core-status', 'drupal.org'), array('allowable-http-hosts' => 'localhost'));
    $expected_error = '{"response_code":403,"error_status":1,"error_log":"Access denied."}';
    $output = $this->getOutput();
    $this->assertJsonStringEqualsJsonString($expected_error, $output, 'Received access denied message for disallowed host.');
    // Check for access denied on invalid IP.
    $this->drush('rest-api-request', array(
        '@none/core-status',
        'drupal.org',
        '8.8.8.8',
    ),
    array(
      'allowable-http-hosts' => 'drupal.org',
      'allowable-ips' => '127.0.0.1,0.0.0.0',
    ));
    $output = $this->getOutput();
    $this->assertJsonStringEqualsJsonString($expected_error, $output, 'Received access denied for disallowed IP address.');
    // Check for access granted when using allowable-http-hosts and
    // allowable-ips.
    $this->drush('rest-api-request', array(
      '@none/core-status',
      'drupal.org',
      '8.8.8.8',
    ),
    array(
      'allowable-http-hosts' => 'drupal.org',
      'allowable-ips' => '127.0.0.1,0.0.0.0,8.8.8.8',
    ));
    $output = $this->getOutput();
    $json = drush_json_decode($output);
    $this->assertEmpty($json['error_log'], 'No errors returned');
    $this->assertEquals(0, $json['error_status'], 'Error status is 0');
  }

  /**
   * Tests for the REST API Server.
   */
  public function testRestApiServer() {
    // Require an action to be specified (e.g. start/stop).
    $this->drush('rest-api-server', array(), array(), NULL, NULL, self::EXIT_ERROR);
    // Stop any existing REST API server processes.
    drush_shell_exec('drush rest-api-server stop');
    // Launch a WebSocket server.
//    exec('drush rest-api-server start --server-type=http -y >/dev/null &');
    // Check status command.
//    drush_shell_exec('curl http://localhost:8888/@none/core-status');
//    $this->assertJson($output, 'Received a JSON response.');
    // Shutdown server.
    drush_shell_exec('drush rest-api-server stop');
  }
}