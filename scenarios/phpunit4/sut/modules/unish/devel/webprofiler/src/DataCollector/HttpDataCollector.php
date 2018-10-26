<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\Http\HttpClientMiddleware;
use Drupal\webprofiler\DrupalDataCollectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects data about http calls during request.
 */
class HttpDataCollector extends DataCollector implements DrupalDataCollectorInterface {

  use StringTranslationTrait, DrupalDataCollectorTrait;

  /**
   * @var \GuzzleHttp\Client
   */
  private $middleware;

  /**
   * @param \Drupal\webprofiler\Http\HttpClientMiddleware $middleware
   */
  public function __construct(HttpClientMiddleware $middleware) {
    $this->middleware = $middleware;

    $this->data['completed'] = [];
    $this->data['failed'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $completed = $this->middleware->getCompletedRequests();
    $failed = $this->middleware->getFailedRequests();

    foreach ($completed as $data) {
      /** @var \GuzzleHttp\Psr7\Request $request */
      $request = $data['request'];
      /** @var \GuzzleHttp\Psr7\Response $response */
      $response = $data['response'];
      /** @var \GuzzleHttp\TransferStats $stats */
      $stats = $request->stats;

      $uri = $request->getUri();
      $this->data['completed'][] = [
        'request' => [
          'method' => $request->getMethod(),
          'uri' => [
            'schema' => $uri->getScheme(),
            'host' => $uri->getHost(),
            'port' => $uri->getPort(),
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'fragment' => $uri->getFragment(),
          ],
          'headers' => $request->getHeaders(),
          'protocol' => $request->getProtocolVersion(),
          'request_target' => $request->getRequestTarget(),
          'stats' => [
            'transferTime' => $stats->getTransferTime(),
            'handlerStats' => $stats->getHandlerStats(),
          ],
        ],
        'response' => [
          'phrase' => $response->getReasonPhrase(),
          'status' => $response->getStatusCode(),
          'headers' => $response->getHeaders(),
          'protocol' => $response->getProtocolVersion(),
        ],
      ];
    }

    foreach ($failed as $data) {
      /** @var \GuzzleHttp\Psr7\Request $request */
      $request = $data['request'];
      /** @var \GuzzleHttp\Psr7\Response $response */
      $response = $data['response'];

      $uri = $request->getUri();
      $failureData = [
        'request' => [
          'method' => $request->getMethod(),
          'uri' => [
            'schema' => $uri->getScheme(),
            'host' => $uri->getHost(),
            'port' => $uri->getPort(),
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'fragment' => $uri->getFragment(),
          ],
          'headers' => $request->getHeaders(),
          'protocol' => $request->getProtocolVersion(),
          'request_target' => $request->getRequestTarget(),
        ],
      ];

      if ($response) {
        $failureData['response'] = [
          'phrase' => $response->getReasonPhrase(),
          'status' => $response->getStatusCode(),
          'headers' => $response->getHeaders(),
          'protocol' => $response->getProtocolVersion(),
        ];
      }

      $this->data['failed'][] = $failureData;
    }
  }

  /**
   * @return int
   */
  public function getCompletedRequestsCount() {
    return count($this->getCompletedRequests());
  }

  /**
   * @return array
   */
  public function getCompletedRequests() {
    return $this->data['completed'];
  }

  /**
   * @return int
   */
  public function getFailedRequestsCount() {
    return count($this->getFailedRequests());
  }

  /**
   * @return array
   */
  public function getFailedRequests() {
    return $this->data['failed'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'http';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Http');
  }

  /**
   * {@inheritdoc}
   */
  public function getPanelSummary() {
    return $this->t(
      'Completed @completed, error @error', [
      '@completed' => $this->getCompletedRequestsCount(),
      '@error' => $this->getFailedRequestsCount(),
    ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon() {
    return 'iVBORw0KGgoAAAANSUhEUgAAABUAAAAcCAYAAACOGPReAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAATlJREFUeNrsleERgjAMha0TdISOwAZ2BEZghI7ABo7AOQFuwAjoBLgBblBbfb0LudByp/4jdw+PNnxtkqYq7/3h13Y8/MF26B9spfo6yAX1QSa6EY2Y0xLrzROgddAMOcgLmuFbhDqyG00W8Rm1JWgEWCG0oQBuJKjGigNUs/xWUJdJheZQJzhZFGkgKWkw1mM8bmTCvOPQcSVXrTA+Ydc0kujXJGg6p5VwrG5BJzb2CLriN9kT74afUylPloQ+kdDPELXpg1qGvwbtURwjFGkkC8RIZw6d2QeO7MII81wRbDm0Z068Zf0G1bxQl8z1UH1zoQwk9NRZdkPoHt+KbZoqa+HYZS4T3iimdEvRDrIF4KIRclBNjk+uSB2/eBJUvR9KSek26BZHOit2zl3oqkV91P6//3N7CTAAIIc/qj2gy4gAAAAASUVORK5CYII=';
  }
}
