<?php

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Class DrushWebSocket.
 */
class DrushWebSocket implements MessageComponentInterface {

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
  public function __construct($allowable_ips = array(), $alllowable_hosts = array()) {
    $this->allowableIps = $allowable_ips;
    $this->allowableHosts = $alllowable_hosts;
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
      // Send the message to the requester, not all clients.
      if ($from == $client) {
        $this->from = $from;
        $this->response = '';
        drush_log(dt('Request from #!resource at IP !ip: !request',
          array(
            '!resource' => $client->resourceId,
            '!ip' => $client->remoteAddress,
            '!request' => trim($request))),
          'ok');
        // TODO: Get values.
        $ip = $client->remoteAddress;
        $host = 'host';
        drush_set_option('request-handler', $this->requestHandler);
        $response = api_process_request($ip, $host, $this->request);
        drush_log(dt('Processed request.'), 'success');
        $client->send(json_encode($response));
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
  public function onError(ConnectionInterface $conn, \Exception $e) {
    drush_set_error('DRUSH_WEB_SOCKET_ERROR', dt('An error occurred: !msg',
       array('!msg' => $e->getMessage())));
    $conn->close();
  }
}
