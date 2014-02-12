<?php

/**
 * @file
 * Code for the Drush Rest API HTTP server.
 */

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\RequestInterface;

/**
 * Class DrushRestApiServerHttp.
 */
class DrushRestApiServerHttp implements MessageComponentInterface {

  protected $allowableIps;
  protected $allowableHosts;
  protected $headers;
  protected $clients;

  /**
   * Constructor.
   */
  public function __construct($allowable_ips, $allowable_hosts, $headers = array()) {
    $this->allowableHosts = $allowable_hosts;
    $this->allowableIps = $allowable_ips;
    $this->headers = $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function onOpen(ConnectionInterface $conn, RequestInterface $request = null) {
    $request_host = $request->getHost();
    $request_ip = $conn->remoteAddress;
    $uri = trim(urldecode(ltrim($request->getPath(), '/')));
    $options = array();
    if (count($this->allowableIps)) {
      $options['allowable-ips'] = $this->allowableIps;
    }
    if (count($this->allowableHosts)) {
      $options['allowable-hosts'] = $this->allowableHosts;
    }
    // Process request.
    drush_log(dt('Request from IP !ip: !request', array('!ip' => $request_ip, '!request' => $uri)), 'ok');
    $result = drush_invoke_process('@none', 'rest-api-request', array(
      $uri,
      $request_host,
     $request_ip,
    ), $options, FALSE);
    $headers = array_merge($this->headers, array(
      'Content-Type' => 'application/json',
    ));
    $status_code = isset($result['response_code']) && !empty($result['response_code']) ? $result['response_code'] : 200;
    // TODO: Set headers and status code.
    drush_log(dt('Processed request.'), 'success');
    $conn->send($result['output']);
    $conn->close();
  }

  /**
   * {@inheritdoc}
   */
  public function onMessage(ConnectionInterface $from, $msg) {
  }

  /**
   * {@inheritdoc}
   */
  public function onClose(ConnectionInterface $conn) {
    drush_log(dt('Closed connection.'), 'ok');
  }

  /**
   * {@inheritdoc}
   */
  public function onError(ConnectionInterface $conn, \Exception $err) {
    drush_set_error('DRUSH_REST_API_HTTP_ERROR', dt('An error occurred: !msg',
      array('!msg' => $err->getMessage())));
  }

}

