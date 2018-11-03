<?php

namespace Drupal\Tests\field_ui\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\field_ui\FieldUI;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\field_ui\FieldUI
 *
 * @group field_ui
 */
class FieldUiTest extends UnitTestCase {

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $pathValidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->pathValidator = $this->getMock('Drupal\Core\Path\PathValidatorInterface');
    $container = new ContainerBuilder();
    $container->set('path.validator', $this->pathValidator);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::getNextDestination
   */
  public function testGetNextDestination() {
    $destinations = ['admin', 'admin/content'];
    $expected_uri = 'base:admin';
    $expected_query = [
      'destinations' => ['admin/content'],
    ];
    $actual = FieldUI::getNextDestination($destinations);
    $this->assertSame($expected_uri, $actual->getUri());
    $this->assertSame($expected_query, $actual->getOption('query'));
  }

  /**
   * @covers ::getNextDestination
   */
  public function testGetNextDestinationEmpty() {
    $destinations = [];
    $actual = FieldUI::getNextDestination($destinations);
    $this->assertNull($actual);
  }

  /**
   * @covers ::getNextDestination
   */
  public function testGetNextDestinationRouteName() {
    $destinations = [['route_name' => 'system.admin'], ['route_name' => 'system.admin_content']];
    $expected_route_name = 'system.admin';
    $expected_query = [
      'destinations' => [['route_name' => 'system.admin_content']],
    ];
    $actual = FieldUI::getNextDestination($destinations);
    $this->assertSame($expected_route_name, $actual->getRouteName());
    $this->assertSame($expected_query, $actual->getOption('query'));
  }

}
