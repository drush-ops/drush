<?php

/**
 * @file
 * Code for processing incoming requests to the Drush REST server.
 */

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Class DrushWebSocket.
 */
class DrushRestApiServerWebSocket implements MessageComponentInterface {

  protected $clients;
  protected $response;
  protected $from;
  protected $request;
  protected $alias;
  protected $command;
  protected $args;
  protected $options;
  protected $allowableIps;
  protected $allowableHosts;

  /**
   * Constructor.
   */
  public function __construct($allowable_ips = '', $allowable_hosts = '', $allowable_commands = '') {
    $this->allowableIps = $allowable_ips;
    $this->allowableHosts = $allowable_hosts;
    $this->allowableCommands = $allowable_commands;
    $this->clients = new \SplObjectStorage();
  }

  /**
   * Actions to take when new connection is opened.
   */
  public function onOpen(ConnectionInterface $conn) {
    // Store the new connection.
    $this->clients->attach($conn);
    drush_log(dt("New connection #!resource received from !ip",
      array('!resource' => $conn->resourceId, '!ip' => $conn->remoteAddress)), 'ok');
  }

  /**
   * Action when message is received.
   */
  public function onMessage(ConnectionInterface $from, $request) {

    foreach ($this->clients as $client) {
      // Send the message to the requester, not all connected clients.
      if ($from == $client) {
        $this->from = $from;
        $this->response = '';
        drush_log(dt('Request from #!resource at IP !ip: !request',
          array(
            '!resource' => $client->resourceId,
            '!ip' => $client->remoteAddress,
            '!request' => trim($request))),
          'ok');
        $options = array();
        if (count($this->allowableIps)) {
          $options['allowable-ips'] = implode(',', $this->allowableIps);
        }
        if (count($this->allowableHosts)) {
          $options['allowable-http-hosts'] = implode(',', $this->allowableHosts);
        }
        if (count($this->allowableCommands)) {
          $options['allowable-commands'] = implode(',', $this->allowableCommands);
        }
        // Process the request.
        $result = drush_invoke_process('@none', 'rest-api-request', array(
          trim($request),
          $client->WebSocket->request->getHost(),
          $client->remoteAddress,
        ), $options, FALSE);
        drush_log(dt('Processed request.'), 'success');
        $client->send($result['output']);
      }
    }
  }

  /**
   * Action when connection is closed.
   */
  public function onClose(ConnectionInterface $conn) {
    // The connection is closed.
    $this->clients->detach($conn);
    drush_log(dt('Closing connection #!resource from IP !ip',
      array('!resource' => $conn->resourceId, '!ip' => $conn->remoteAddress)), 'ok');
  }

  /**
   * Log errors.
   */
  public function onError(ConnectionInterface $conn, \Exception $err) {
    drush_set_error('DRUSH_REST_API_WEBSOCKET_ERROR', dt('An error occurred: !msg',
       array('!msg' => $err->getMessage())));
    $conn->close();
  }
}
