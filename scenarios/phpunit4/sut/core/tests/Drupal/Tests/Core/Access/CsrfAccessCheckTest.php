<?php

namespace Drupal\Tests\Core\Access;

use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Drupal\Core\Access\CsrfAccessCheck;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Access\CsrfAccessCheck
 * @group Access
 */
class CsrfAccessCheckTest extends UnitTestCase {

  /**
   * The mock CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $csrfToken;

  /**
   * The access checker.
   *
   * @var \Drupal\Core\Access\CsrfAccessCheck
   */
  protected $accessCheck;

  /**
   * The mock route match.
   *
   * @var \Drupal\Core\RouteMatch\RouteMatchInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $routeMatch;

  protected function setUp() {
    $this->csrfToken = $this->getMockBuilder('Drupal\Core\Access\CsrfTokenGenerator')
      ->disableOriginalConstructor()
      ->getMock();

    $this->routeMatch = $this->getMock('Drupal\Core\Routing\RouteMatchInterface');

    $this->accessCheck = new CsrfAccessCheck($this->csrfToken);
  }

  /**
   * Tests the access() method with a valid token.
   */
  public function testAccessTokenPass() {
    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with('test_query', 'test-path/42')
      ->will($this->returnValue(TRUE));

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->will($this->returnValue(['node' => 42]));

    $route = new Route('/test-path/{node}', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path/42?token=test_query');

    $this->assertEquals(AccessResult::allowed()->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

  /**
   * @covers ::access
   */
  public function testCsrfTokenInvalid() {
    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with('test_query', 'test-path')
      ->will($this->returnValue(FALSE));

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->will($this->returnValue([]));

    $route = new Route('/test-path', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path?token=test_query');

    $this->assertEquals(AccessResult::forbidden("'csrf_token' URL query argument is invalid.")->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

  /**
   * @covers ::access
   */
  public function testCsrfTokenMissing() {
    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with('', 'test-path')
      ->will($this->returnValue(FALSE));

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->will($this->returnValue([]));

    $route = new Route('/test-path', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path');
    $this->assertEquals(AccessResult::forbidden("'csrf_token' URL query argument is missing.")->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

}
