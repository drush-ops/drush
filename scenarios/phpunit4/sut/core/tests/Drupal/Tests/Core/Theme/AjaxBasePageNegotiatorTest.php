<?php

namespace Drupal\Tests\Core\Theme;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Theme\AjaxBasePageNegotiator;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\Core\Theme\AjaxBasePageNegotiator
 * @group Theme
 */
class AjaxBasePageNegotiatorTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Theme\AjaxBasePageNegotiator
   *
   * The AJAX base page negotiator.
   */
  protected $negotiator;

  /**
   * @var \Drupal\Core\Access\CsrfTokenGenerator|\Prophecy\Prophecy\ProphecyInterface
   *
   * The CSRF token generator.
   */
  protected $tokenGenerator;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   *
   * The request stack.
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->tokenGenerator = $this->prophesize(CsrfTokenGenerator::class);
    $config_factory = $this->getConfigFactoryStub(['system.theme' => ['default' => 'bartik']]);
    $this->requestStack = new RequestStack();
    $this->negotiator = new AjaxBasePageNegotiator($this->tokenGenerator->reveal(), $config_factory, $this->requestStack);
  }

  /**
   * @covers ::applies
   * @dataProvider providerTestApplies
   */
  public function testApplies($request_data, $expected) {
    $request = new Request([], $request_data);
    $route_match = RouteMatch::createFromRequest($request);
    $this->requestStack->push($request);

    $result = $this->negotiator->applies($route_match);
    $this->assertSame($expected, $result);
  }

  public function providerTestApplies() {
    $data = [];
    $data['empty'] = [[], FALSE];
    $data['no_theme'] = [['ajax_page_state' => ['theme' => '', 'theme_token' => '']], FALSE];
    $data['valid_theme_empty_theme_token'] = [['ajax_page_state' => ['theme' => 'seven', 'theme_token' => '']], TRUE];
    $data['valid_theme_valid_theme_token'] = [['ajax_page_state' => ['theme' => 'seven', 'theme_token' => 'valid_theme_token']], TRUE];
    return $data;
  }

  /**
   * @covers ::determineActiveTheme
   */
  public function testDetermineActiveThemeValidToken() {
    $theme = 'seven';
    $theme_token = 'valid_theme_token';

    $request = new Request([], ['ajax_page_state' => ['theme' => $theme, 'theme_token' => $theme_token]]);
    $this->requestStack->push($request);
    $route_match = RouteMatch::createFromRequest($request);

    $this->tokenGenerator->validate($theme_token, $theme)->willReturn(TRUE);

    $result = $this->negotiator->determineActiveTheme($route_match);
    $this->assertSame($theme, $result);
  }

  /**
   * @covers ::determineActiveTheme
   */
  public function testDetermineActiveThemeInvalidToken() {
    $theme = 'seven';
    $theme_token = 'invalid_theme_token';

    $request = new Request([], ['ajax_page_state' => ['theme' => $theme, 'theme_token' => $theme_token]]);
    $this->requestStack->push($request);
    $route_match = RouteMatch::createFromRequest($request);

    $this->tokenGenerator->validate($theme_token, $theme)->willReturn(FALSE);

    $result = $this->negotiator->determineActiveTheme($route_match);
    $this->assertSame(NULL, $result);
  }

  /**
   * @covers ::determineActiveTheme
   */
  public function testDetermineActiveThemeDefaultTheme() {
    $theme = 'bartik';
    // When the theme is the system default, an empty string is provided as the
    // theme token. See system_js_settings_alter().
    $theme_token = '';

    $request = new Request([], ['ajax_page_state' => ['theme' => $theme, 'theme_token' => $theme_token]]);
    $this->requestStack->push($request);
    $route_match = RouteMatch::createFromRequest($request);

    $this->tokenGenerator->validate(Argument::cetera())->shouldNotBeCalled();

    $result = $this->negotiator->determineActiveTheme($route_match);
    $this->assertSame($theme, $result);
  }

}
