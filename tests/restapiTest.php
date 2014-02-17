<?php

/**
 * @file
 * Tests for rest-api commands.
 *
 * @group commands
 */

use Guzzle\Http\Client;

define('DRUSH_REST_API_ACCESS_DENIED_MSG', '{"response_code":403,"error_status":1,"error_log":"Access denied."}');
define('DRUSH_REST_API_ERROR_MSG', '{"error_status":1,"error_log":"Invalid request. REST API requests must use this format: {@alias}\/{command}\/{argument}\/{argument_two}?{option=value\u0026option2=value2}"}');

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
    $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ERROR_MSG, $output, 'Received expected JSON');
    // Now check a valid request, '@none/core-status?format=json'.
    $this->drush('rest-api-request', array('@none/core-status?format=json'));
    $output = $this->getOutput();
    $this->assertJson($output, 'Received valid JSON');
    $json = json_decode($output, TRUE);
    $this->assertEquals(0, $json['error_status'], 'Successful request.');
    $this->assertEmpty($json['error_log'], 'Error log is empty.');
    $this->assertJson($json['output'], 'Returned JSON data as requested.');
    // Make an invalid request, using a bogus alias.
    $this->drush('rest-api-request', array('@blah/core-status'));
    $output = $this->getOutput();
    $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ERROR_MSG, $output, 'Received error message.');
    // Check for access denied on invalid host.
    $this->drush('rest-api-request', array('@none/core-status', 'drupal.org'), array('allowable-http-hosts' => 'localhost'));
    $output = $this->getOutput();
    $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ACCESS_DENIED_MSG, $output, 'Received access denied message for disallowed host.');
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
    $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ACCESS_DENIED_MSG, $output, 'Received access denied for disallowed IP address.');
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
    $json = json_decode($output, TRUE);
    $this->assertEmpty($json['error_log'], 'No errors returned');
    $this->assertEquals(0, $json['error_status'], 'Error status is 0');
  }

  /**
   * Tests for the REST API Http Server.
   */
  public function testRestApiHttpServer() {
    // Launch a HTTP server.
    exec('drush rest-api-server start --server-type=http -y >/dev/null &');
    sleep(2);
    // Check status command.
    $client = new Client('http://localhost:8888');
    $request = $client->get('@none/core-status?format=json');
    $response = $request->send();
    $output = (string) $response->getBody();
    $this->assertEquals(200, $response->getStatusCode(), '200 status code.');
    $this->assertJson($output, 'Received a JSON response.');
    $json = json_decode($output, TRUE);
    $this->assertEquals(0, $json['error_status'], 'Successful request.');
    $this->assertEmpty($json['error_log'], 'Error log is empty.');
    $this->assertJson($json['output'], 'Returned JSON data as requested.');
    // Check an invalid request.
    $client = new Client('http://localhost:8888');
    $request = $client->get('/@blah/');
    try {
      // Guzzle wil throw an exception.
      $request->send();
    }
    catch (Exception $e) {
      $response = $e->getResponse();
      $output = (string) $response->getBody();
      $this->assertJson($output, 'Received a JSON response.');
      $this->assertEquals(400, $response->getStatusCode(), '400 status code.');
      $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ERROR_MSG, $output, 'Received an error response.');
    }
    // Check an access denied request.
    $this->drush('rest-api-server', array('stop'));
    sleep(2);
    exec('drush rest-api-server start --server-type=http --allowable-ips=8.8.8.8 -y >/dev/null &');
    sleep(2);
    $client = new Client('http://localhost:8888');
    $request = $client->get('/@none/core-status');
    try {
      // Guzzle wil throw an exception.
      $request->send();
    }
    catch (Exception $e) {
      $response = $e->getResponse();
      $output = (string) $response->getBody();
      $this->assertJson($output, 'Received a JSON response.');
      $this->assertEquals(403, $response->getStatusCode(), '403 status code.');
      $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ACCESS_DENIED_MSG, $output, 'Received an access denied response.');
    }
    // Check access denied for allowable hosts.
    $this->drush('rest-api-server', array('stop'));
    sleep(2);
    exec('drush rest-api-server start --server-type=http --allowable-http-hosts=drush.ws -y >/dev/null &');
    sleep(2);
    $client = new Client('http://localhost:8888');
    $request = $client->get('/@none/core-status');
    try {
      // Guzzle wil throw an exception.
      $request->send();
    }
    catch (Exception $e) {
      $response = $e->getResponse();
      $output = (string) $response->getBody();
      $this->assertJson($output, 'Received a JSON response.');
      $this->assertEquals(403, $response->getStatusCode(), '403 status code.');
      $this->assertJsonStringEqualsJsonString(DRUSH_REST_API_ACCESS_DENIED_MSG, $output, 'Received an access denied response.');
    }
    // Check if one or multiple headers are set correctly.
    $this->drush('rest-api-server', array('stop'));
    sleep(2);
    exec('drush rest-api-server start --server-type=http --headers="Access-Control-Allow-Origin:
 *, Drush: Drush" -y >/dev/null &');
    sleep(2);
    $client = new Client('http://localhost:8888');
    $request = $client->get('/@none/core-status');
    $response = $request->send();
    $this->assertEquals($response->hasHeader('Access-Control-Allow-Origin'), 1, 'Access-Control-Allow-Origin custom header is set.');
    $this->assertEquals($response->hasHeader('Drush'), 1, 'Custom Drush header is set.');
    $this->drush('rest-api-server', array('stop'));
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->log('Stopping any existing Drush REST API server processes.');
    exec('drush rest-api-server stop');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->log('Shutting down any existing Drush REST API server processes');
    exec('drush rest-api-server stop');
  }
}