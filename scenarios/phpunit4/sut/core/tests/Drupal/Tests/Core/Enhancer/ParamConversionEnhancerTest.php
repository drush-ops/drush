<?php

namespace Drupal\Tests\Core\Enhancer;

use Drupal\Core\Routing\Enhancer\ParamConversionEnhancer;
use Drupal\Tests\UnitTestCase;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\Core\Routing\Enhancer\ParamConversionEnhancer
 * @group Enhancer
 */
class ParamConversionEnhancerTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Routing\Enhancer\ParamConversionEnhancer
   */
  protected $paramConversionEnhancer;

  /**
   * @var \Drupal\Core\ParamConverter\ParamConverterManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $paramConverterManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->paramConverterManager = $this->getMock('Drupal\Core\ParamConverter\ParamConverterManagerInterface');
    $this->paramConversionEnhancer = new ParamConversionEnhancer($this->paramConverterManager);
  }

  /**
   * @covers ::enhance
   */
  public function testEnhance() {
    $route = new Route('/test/{id}/{literal}/{null}');

    $raw_variables = [
      'id' => 1,
      'literal' => 'this is a literal',
      'null' => NULL,
    ];
    $defaults = [
      RouteObjectInterface::ROUTE_OBJECT => $route,
    ] + $raw_variables;

    $expected = $defaults;
    $expected['id'] = 'something_better!';
    $expected['_raw_variables'] = new ParameterBag($raw_variables);

    $this->paramConverterManager->expects($this->once())
      ->method('convert')
      ->with($this->isType('array'))
      ->will($this->returnValue($expected));

    $result = $this->paramConversionEnhancer->enhance($defaults, new Request());

    $this->assertEquals($expected, $result);

    // Now run with the results as the new defaults to ensure that the
    // conversion is just run once.
    $result = $this->paramConversionEnhancer->enhance($result, new Request());

    $this->assertEquals($expected, $result);
  }

  /**
   * @covers ::copyRawVariables
   */
  public function testCopyRawVariables() {
    $route = new Route('/test/{id}');
    $defaults = [
      RouteObjectInterface::ROUTE_OBJECT => $route,
      'id' => '1',
    ];
    // Set one default to mirror another by reference.
    $defaults['bar'] = &$defaults['id'];
    $this->paramConverterManager->expects($this->any())
      ->method('convert')
      ->with($this->isType('array'))
      ->will($this->returnCallback(function ($defaults) {
        // Convert the mirrored default to another value.
        $defaults['bar'] = '2';
        return $defaults;
      }));
    $expected = new ParameterBag(['id' => 1]);
    $result = $this->paramConversionEnhancer->enhance($defaults, new Request());
    $this->assertEquals($result['_raw_variables'], $expected);
  }

}
