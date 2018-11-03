<?php

/**
 * @file
 * Contains \Drupal\Tests\system\Unit\Breadcrumbs\PathBasedBreadcrumbBuilderTest.
 */

namespace Drupal\Tests\system\Unit\Breadcrumbs;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Link;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\system\PathBasedBreadcrumbBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\system\PathBasedBreadcrumbBuilder
 * @group system
 */
class PathBasedBreadcrumbBuilderTest extends UnitTestCase {

  /**
   * The path based breadcrumb builder object to test.
   *
   * @var \Drupal\system\PathBasedBreadcrumbBuilder
   */
  protected $builder;

  /**
   * The mocked title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $titleResolver;

  /**
   * The mocked access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $accessManager;

  /**
   * The request matching mock object.
   *
   * @var \Symfony\Component\Routing\Matcher\RequestMatcherInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $requestMatcher;

  /**
   * The mocked route request context.
   *
   * @var \Drupal\Core\Routing\RequestContext|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $context;

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentUser;

  /**
   * The mocked path processor.
   *
   * @var \Drupal\Core\PathProcessor\InboundPathProcessorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pathProcessor;

  /**
   * The mocked current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $currentPath;

  /**
   * The mocked path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pathMatcher;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    parent::setUp();

    $this->requestMatcher = $this->getMock('\Symfony\Component\Routing\Matcher\RequestMatcherInterface');

    $config_factory = $this->getConfigFactoryStub(['system.site' => ['front' => 'test_frontpage']]);

    $this->pathProcessor = $this->getMock('\Drupal\Core\PathProcessor\InboundPathProcessorInterface');
    $this->context = $this->getMock('\Drupal\Core\Routing\RequestContext');

    $this->accessManager = $this->getMock('\Drupal\Core\Access\AccessManagerInterface');
    $this->titleResolver = $this->getMock('\Drupal\Core\Controller\TitleResolverInterface');
    $this->currentUser = $this->getMock('Drupal\Core\Session\AccountInterface');
    $this->currentPath = $this->getMockBuilder('Drupal\Core\Path\CurrentPathStack')
      ->disableOriginalConstructor()
      ->getMock();

    $this->pathMatcher = $this->getMock(PathMatcherInterface::class);

    $this->builder = new TestPathBasedBreadcrumbBuilder(
      $this->context,
      $this->accessManager,
      $this->requestMatcher,
      $this->pathProcessor,
      $config_factory,
      $this->titleResolver,
      $this->currentUser,
      $this->currentPath,
      $this->pathMatcher
    );

    $this->builder->setStringTranslation($this->getStringTranslationStub());

    $cache_contexts_manager = $this->getMockBuilder('Drupal\Core\Cache\Context\CacheContextsManager')
      ->disableOriginalConstructor()
      ->getMock();
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests the build method on the frontpage.
   *
   * @covers ::build
   */
  public function testBuildOnFrontpage() {
    $this->pathMatcher->expects($this->once())
      ->method('isFrontPage')
      ->willReturn(TRUE);

    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));
    $this->assertEquals([], $breadcrumb->getLinks());
    $this->assertEquals(['url.path.parent'], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests the build method with one path element.
   *
   * @covers ::build
   */
  public function testBuildWithOnePathElement() {
    $this->context->expects($this->once())
      ->method('getPathInfo')
      ->will($this->returnValue('/example'));

    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));
    $this->assertEquals([0 => new Link('Home', new Url('<front>'))], $breadcrumb->getLinks());
    $this->assertEquals(['url.path.parent'], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests the build method with two path elements.
   *
   * @covers ::build
   * @covers ::getRequestForPath
   */
  public function testBuildWithTwoPathElements() {
    $this->context->expects($this->once())
      ->method('getPathInfo')
      ->will($this->returnValue('/example/baz'));
    $this->setupStubPathProcessor();

    $route_1 = new Route('/example');

    $this->requestMatcher->expects($this->exactly(1))
      ->method('matchRequest')
      ->will($this->returnCallback(function (Request $request) use ($route_1) {
        if ($request->getPathInfo() == '/example') {
          return [
            RouteObjectInterface::ROUTE_NAME => 'example',
            RouteObjectInterface::ROUTE_OBJECT => $route_1,
            '_raw_variables' => new ParameterBag([]),
          ];
        }
      }));

    $this->setupAccessManagerToAllow();

    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));
    $this->assertEquals([0 => new Link('Home', new Url('<front>')), 1 => new Link('Example', new Url('example'))], $breadcrumb->getLinks());
    $this->assertEquals(['url.path.parent', 'user.permissions'], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests the build method with three path elements.
   *
   * @covers ::build
   * @covers ::getRequestForPath
   */
  public function testBuildWithThreePathElements() {
    $this->context->expects($this->once())
      ->method('getPathInfo')
      ->will($this->returnValue('/example/bar/baz'));
    $this->setupStubPathProcessor();

    $route_1 = new Route('/example/bar');
    $route_2 = new Route('/example');

    $this->requestMatcher->expects($this->exactly(2))
      ->method('matchRequest')
      ->will($this->returnCallback(function (Request $request) use ($route_1, $route_2) {
        if ($request->getPathInfo() == '/example/bar') {
          return [
            RouteObjectInterface::ROUTE_NAME => 'example_bar',
            RouteObjectInterface::ROUTE_OBJECT => $route_1,
            '_raw_variables' => new ParameterBag([]),
          ];
        }
        elseif ($request->getPathInfo() == '/example') {
          return [
            RouteObjectInterface::ROUTE_NAME => 'example',
            RouteObjectInterface::ROUTE_OBJECT => $route_2,
            '_raw_variables' => new ParameterBag([]),
          ];
        }
      }));

    $this->accessManager->expects($this->any())
      ->method('check')
      ->willReturnOnConsecutiveCalls(
        AccessResult::allowed()->cachePerPermissions(),
        AccessResult::allowed()->addCacheContexts(['bar'])->addCacheTags(['example'])
      );
    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));
    $this->assertEquals([
      new Link('Home', new Url('<front>')),
      new Link('Example', new Url('example')),
      new Link('Bar', new Url('example_bar')),
    ], $breadcrumb->getLinks());
    $this->assertEquals(['bar', 'url.path.parent', 'user.permissions'], $breadcrumb->getCacheContexts());
    $this->assertEquals(['example'], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests that exceptions during request matching are caught.
   *
   * @covers ::build
   * @covers ::getRequestForPath
   *
   * @dataProvider providerTestBuildWithException
   */
  public function testBuildWithException($exception_class, $exception_argument) {
    $this->context->expects($this->once())
      ->method('getPathInfo')
      ->will($this->returnValue('/example/bar'));
    $this->setupStubPathProcessor();

    $this->requestMatcher->expects($this->any())
      ->method('matchRequest')
      ->will($this->throwException(new $exception_class($exception_argument)));

    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));

    // No path matched, though at least the frontpage is displayed.
    $this->assertEquals([0 => new Link('Home', new Url('<front>'))], $breadcrumb->getLinks());
    $this->assertEquals(['url.path.parent'], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Provides exception types for testBuildWithException.
   *
   * @return array
   *   The list of exception test cases.
   *
   * @see \Drupal\Tests\system\Unit\Breadcrumbs\PathBasedBreadcrumbBuilderTest::testBuildWithException()
   */
  public function providerTestBuildWithException() {
    return [
      ['Drupal\Core\ParamConverter\ParamNotConvertedException', ''],
      ['Symfony\Component\Routing\Exception\MethodNotAllowedException', []],
      ['Symfony\Component\Routing\Exception\ResourceNotFoundException', ''],
    ];
  }

  /**
   * Tests the build method with a non processed path.
   *
   * @covers ::build
   * @covers ::getRequestForPath
   */
  public function testBuildWithNonProcessedPath() {
    $this->context->expects($this->once())
      ->method('getPathInfo')
      ->will($this->returnValue('/example/bar'));

    $this->pathProcessor->expects($this->once())
      ->method('processInbound')
      ->will($this->returnValue(FALSE));

    $this->requestMatcher->expects($this->any())
      ->method('matchRequest')
      ->will($this->returnValue([]));

    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));

    // No path matched, though at least the frontpage is displayed.
    $this->assertEquals([0 => new Link('Home', new Url('<front>'))], $breadcrumb->getLinks());
    $this->assertEquals(['url.path.parent'], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Tests the applied method.
   *
   * @covers ::applies
   */
  public function testApplies() {
    $this->assertTrue($this->builder->applies($this->getMock('Drupal\Core\Routing\RouteMatchInterface')));
  }

  /**
   * Tests the breadcrumb for a user path.
   *
   * @covers ::build
   * @covers ::getRequestForPath
   */
  public function testBuildWithUserPath() {
    $this->context->expects($this->once())
      ->method('getPathInfo')
      ->will($this->returnValue('/user/1/edit'));
    $this->setupStubPathProcessor();

    $route_1 = new Route('/user/1');

    $this->requestMatcher->expects($this->exactly(1))
      ->method('matchRequest')
      ->will($this->returnCallback(function (Request $request) use ($route_1) {
        if ($request->getPathInfo() == '/user/1') {
          return [
            RouteObjectInterface::ROUTE_NAME => 'user_page',
            RouteObjectInterface::ROUTE_OBJECT => $route_1,
            '_raw_variables' => new ParameterBag([]),
          ];
        }
      }));

    $this->setupAccessManagerToAllow();
    $this->titleResolver->expects($this->once())
      ->method('getTitle')
      ->with($this->anything(), $route_1)
      ->will($this->returnValue('Admin'));

    $breadcrumb = $this->builder->build($this->getMock('Drupal\Core\Routing\RouteMatchInterface'));
    $this->assertEquals([0 => new Link('Home', new Url('<front>')), 1 => new Link('Admin', new Url('user_page'))], $breadcrumb->getLinks());
    $this->assertEquals(['url.path.parent', 'user.permissions'], $breadcrumb->getCacheContexts());
    $this->assertEquals([], $breadcrumb->getCacheTags());
    $this->assertEquals(Cache::PERMANENT, $breadcrumb->getCacheMaxAge());
  }

  /**
   * Setup the access manager to always allow access to routes.
   */
  public function setupAccessManagerToAllow() {
    $this->accessManager->expects($this->any())
      ->method('check')
      ->willReturn((new AccessResultAllowed())->cachePerPermissions());
  }

  protected function setupStubPathProcessor() {
    $this->pathProcessor->expects($this->any())
      ->method('processInbound')
      ->will($this->returnArgument(0));
  }

}

/**
 * Helper class for testing purposes only.
 */
class TestPathBasedBreadcrumbBuilder extends PathBasedBreadcrumbBuilder {

  public function setStringTranslation(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  public function setLinkGenerator(LinkGeneratorInterface $link_generator) {
    $this->linkGenerator = $link_generator;
  }

}
