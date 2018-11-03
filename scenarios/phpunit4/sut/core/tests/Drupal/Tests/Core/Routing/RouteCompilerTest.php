<?php

namespace Drupal\Tests\Core\Routing;

use Drupal\Core\Routing\RouteCompiler;
use Symfony\Component\Routing\Route;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Routing\RouteCompiler
 * @group Routing
 */
class RouteCompilerTest extends UnitTestCase {

  /**
   * Tests RouteCompiler::getFit().
   *
   * @param string $path
   *   A path whose fit will be calculated in the test.
   * @param int $expected
   *   The expected fit returned by RouteCompiler::getFit()
   *
   * @dataProvider providerTestGetFit
   */
  public function testGetFit($path, $expected) {
    $route_compiler = new RouteCompiler();
    $result = $route_compiler->getFit($path);
    $this->assertSame($expected, $result);
  }

  /**
   * Provides data for RouteCompilerTest::testGetFit()
   *
   * @return array
   *   An array of arrays, where each inner array has the path whose fit is to
   *   be calculated as the first value and the expected fit as the second
   *   value.
   */
  public function providerTestGetFit() {
    return [
      ['test', 1],
      ['/testwithleadingslash', 1],
      ['testwithtrailingslash/', 1],
      ['/testwithslashes/', 1],
      ['test/with/multiple/parts', 15],
      ['test/with/{some}/slugs', 13],
      ['test/very/long/path/that/drupal/7/could/not/have/handled', 2047],
    ];
  }

  /**
   * Confirms that a route compiles properly with the necessary data.
   */
  public function testCompilation() {
    $route = new Route('/test/{something}/more');
    $route->setOption('compiler_class', 'Drupal\Core\Routing\RouteCompiler');
    $compiled = $route->compile();

    $this->assertEquals($compiled->getFit(), 5 /* That's 101 binary*/, 'The fit was incorrect.');
    $this->assertEquals($compiled->getPatternOutline(), '/test/%/more', 'The pattern outline was not correct.');
  }

  /**
   * Confirms that a compiled route with default values has the correct outline.
   */
  public function testCompilationDefaultValue() {
    // Because "here" has a default value, it should not factor into the outline
    // or the fitness.
    $route = new Route('/test/{something}/more/{here}', [
      'here' => 'there',
    ]);
    $route->setOption('compiler_class', 'Drupal\Core\Routing\RouteCompiler');
    $compiled = $route->compile();

    $this->assertEquals($compiled->getFit(), 5 /* That's 101 binary*/, 'The fit was not correct.');
    $this->assertEquals($compiled->getPatternOutline(), '/test/%/more', 'The pattern outline was not correct.');
  }

}
