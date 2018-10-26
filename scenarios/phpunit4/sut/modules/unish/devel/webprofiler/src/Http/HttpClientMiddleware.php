<?php

namespace Drupal\webprofiler\Http;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\RequestInterface;

/**
 * Class HttpClientMiddleware
 */
class HttpClientMiddleware {

  /**
   * @var array
   */
  private $completedRequests;

  /**
   * @var array
   */
  private $failedRequests;

  /**
   *
   */
  public function __construct() {
    $this->completedRequests = [];
    $this->failedRequests = [];
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {

        $options['on_stats'] = function (TransferStats $stats) use ($request) {
          $request->stats = $stats;
        };

        return $handler($request, $options)->then(
          function ($response) use ($request) {

            $this->completedRequests[] = [
              'request' => $request,
              'response' => $response,
            ];

            return $response;
          },
          function ($reason) use ($request) {
            $response = $reason instanceof RequestException
              ? $reason->getResponse()
              : null;

            $this->failedRequests[] = [
              'request' => $request,
              'response' => $response,
              'message' => $reason->getMessage(),
            ];

            return \GuzzleHttp\Promise\rejection_for($reason);
          }
        );
      };
    };
  }

  /**
   * @return array
   */
  public function getCompletedRequests() {
    return $this->completedRequests;
  }

  /**
   * @return array
   */
  public function getFailedRequests() {
    return $this->failedRequests;
  }
}
