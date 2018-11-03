<?php

namespace Drupal\Tests\rest\Functional;

use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait for ResourceTestBase subclasses testing $auth=cookie.
 *
 * Characteristics:
 * - After performing a valid "log in" request, the server responds with a 2xx
 *   status code and a 'Set-Cookie' response header. This cookie is what
 *   continues to identify the user in subsequent requests.
 * - When accessing a URI that requires authentication without being
 *   authenticated, a standard 403 response must be sent.
 * - Because of the reliance on cookies, and the fact that user agents send
 *   cookies with every request, this is vulnerable to CSRF attacks. To mitigate
 *   this, the response for the "log in" request contains a CSRF token that must
 *   be sent with every unsafe (POST/PATCH/DELETE) HTTP request.
 */
trait CookieResourceTestTrait {

  /**
   * The session cookie.
   *
   * @see ::initAuthentication
   *
   * @var string
   */
  protected $sessionCookie;

  /**
   * The CSRF token.
   *
   * @see ::initAuthentication
   *
   * @var string
   */
  protected $csrfToken;

  /**
   * The logout token.
   *
   * @see ::initAuthentication
   *
   * @var string
   */
  protected $logoutToken;

  /**
   * {@inheritdoc}
   */
  protected function initAuthentication() {
    $user_login_url = Url::fromRoute('user.login.http')
      ->setRouteParameter('_format', static::$format);

    $request_body = [
      'name' => $this->account->name->value,
      'pass' => $this->account->passRaw,
    ];

    $request_options[RequestOptions::BODY] = $this->serializer->encode($request_body, static::$format);
    $request_options[RequestOptions::HEADERS] = [
      'Content-Type' => static::$mimeType,
    ];
    $response = $this->request('POST', $user_login_url, $request_options);

    // Parse and store the session cookie.
    $this->sessionCookie = explode(';', $response->getHeader('Set-Cookie')[0], 2)[0];

    // Parse and store the CSRF token and logout token.
    $data = $this->serializer->decode((string) $response->getBody(), static::$format);
    $this->csrfToken = $data['csrf_token'];
    $this->logoutToken = $data['logout_token'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAuthenticationRequestOptions($method) {
    $request_options[RequestOptions::HEADERS]['Cookie'] = $this->sessionCookie;
    // @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
    if (!in_array($method, ['HEAD', 'GET', 'OPTIONS', 'TRACE'])) {
      $request_options[RequestOptions::HEADERS]['X-CSRF-Token'] = $this->csrfToken;
    }
    return $request_options;
  }

  /**
   * {@inheritdoc}
   */
  protected function assertResponseWhenMissingAuthentication($method, ResponseInterface $response) {
    // Requests needing cookie authentication but missing it results in a 403
    // response. The cookie authentication mechanism sets no response message.
    // Hence, effectively, this is just the 403 response that one gets as the
    // anonymous user trying to access a certain REST resource.
    // @see \Drupal\user\Authentication\Provider\Cookie
    // @todo https://www.drupal.org/node/2847623
    if ($method === 'GET') {
      $expected_cookie_403_cacheability = $this->getExpectedUnauthorizedAccessCacheability();
      // - \Drupal\Core\EventSubscriber\AnonymousUserResponseSubscriber applies
      //   to cacheable anonymous responses: it updates their cacheability.
      // - A 403 response to a GET request is cacheable.
      // Therefore we must update our cacheability expectations accordingly.
      if (in_array('user.permissions', $expected_cookie_403_cacheability->getCacheContexts(), TRUE)) {
        $expected_cookie_403_cacheability->addCacheTags(['config:user.role.anonymous']);
      }
      // @todo Fix \Drupal\block\BlockAccessControlHandler::mergeCacheabilityFromConditions() in https://www.drupal.org/node/2867881
      if (static::$entityTypeId === 'block') {
        $expected_cookie_403_cacheability->setCacheTags(str_replace('user:2', 'user:0', $expected_cookie_403_cacheability->getCacheTags()));
      }
      $this->assertResourceErrorResponse(403, FALSE, $response, $expected_cookie_403_cacheability->getCacheTags(), $expected_cookie_403_cacheability->getCacheContexts(), 'MISS', 'MISS');
    }
    else {
      $this->assertResourceErrorResponse(403, FALSE, $response);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function assertAuthenticationEdgeCases($method, Url $url, array $request_options) {
    // X-CSRF-Token request header is unnecessary for safe and side effect-free
    // HTTP methods. No need for additional assertions.
    // @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
    if (in_array($method, ['HEAD', 'GET', 'OPTIONS', 'TRACE'])) {
      return;
    }

    unset($request_options[RequestOptions::HEADERS]['X-CSRF-Token']);

    // DX: 403 when missing X-CSRF-Token request header.
    $response = $this->request($method, $url, $request_options);
    $this->assertResourceErrorResponse(403, 'X-CSRF-Token request header is missing', $response);

    $request_options[RequestOptions::HEADERS]['X-CSRF-Token'] = 'this-is-not-the-token-you-are-looking-for';

    // DX: 403 when invalid X-CSRF-Token request header.
    $response = $this->request($method, $url, $request_options);
    $this->assertResourceErrorResponse(403, 'X-CSRF-Token request header is invalid', $response);

    $request_options[RequestOptions::HEADERS]['X-CSRF-Token'] = $this->csrfToken;
  }

}
