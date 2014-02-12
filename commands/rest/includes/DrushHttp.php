<?php

/**
 * @file
 * Code for the Drush Rest API HTTP server.
 */

/**
 * Class DrushRestApiServerHttp.
 */
class DrushRestApiServerHttp extends HTTPServer
{
  protected $allowableIps;
  protected $allowableHosts;
  protected $headers;

  /**
   * Constructor.
   *
   * @param int $port
   * @param string $hostname
   * @param array $allowable_ips
   * @param array $allowable_hosts
   * @param array $headers
   */
  function __construct($port, $hostname, $allowable_ips, $allowable_hosts, $headers = array())
  {
    parent::__construct(array(
      'port' => $port,
      'host' => $hostname,
    ));
    $this->allowableHosts = $allowable_hosts;
    $this->allowableIps = $allowable_ips;
    $this->headers = $headers;
  }

  /**
   * Routes the incoming request.
   *
   * @param array $request
   *   The request array.
   *
   * @return HTTPResponse
   */
  public function route_request($request) {
    $uri = urldecode(ltrim($request->request_uri, '/'));
    $options = array();
    if (count($this->allowableIps)) {
      $options['allowable-ips'] = $this->allowableIps;
    }
    if (count($this->allowableHosts)) {
      $options['allowable-hosts'] = $this->allowableHosts;
    }
    // Process request.
    $result = drush_invoke_process('@none', 'rest-api-request', array(
      trim($uri),
      $request->headers['Host'],
      $request->remote_addr,
    ), $options, FALSE);
    $file = drush_save_data_to_temp_file($result['output']);
    return $this->get_static_response($request, $file);
  }

  /**
   * Return a response based on the data saved to a temp file.
   *
   * @param array $request
   *   The original request.
   * @param string $local_path
   *   The output of drush_invoke_process().
   *
   * @return HTTPResponse
   */
  public function get_static_response($request, $local_path) {
    // Check if error and set response code appropriately.
    $headers = array_merge($this->headers, array(
      'Content-Type' => 'application/json',
      'Content-Length' => filesize($local_path),
    ));
    $data = drush_json_decode(file_get_contents($local_path));
    return $this->response($data['error_status'] == 0 ? 200 : 400,
      fopen($local_path, 'rb'),
      $headers
    );
  }
}
