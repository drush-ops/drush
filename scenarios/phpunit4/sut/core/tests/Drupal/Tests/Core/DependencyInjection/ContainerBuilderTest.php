<?php

namespace Drupal\Tests\Core\DependencyInjection;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\Core\DependencyInjection\Fixture\BarClass;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @coversDefaultClass \Drupal\Core\DependencyInjection\ContainerBuilder
 * @group DependencyInjection
 */
class ContainerBuilderTest extends UnitTestCase {

  /**
   * @covers ::get
   */
  public function testGet() {
    $container = new ContainerBuilder();
    $container->register('bar', 'Drupal\Tests\Core\DependencyInjection\Fixture\BarClass');

    $result = $container->get('bar');
    $this->assertTrue($result instanceof BarClass);
  }

  /**
   * @covers ::set
   */
  public function testSet() {
    $container = new ContainerBuilder();
    $class = new BarClass();
    $container->set('bar', $class);
    $this->assertEquals('bar', $class->_serviceId);
  }

  /**
   * @covers ::set
   */
  public function testSetException() {
    $container = new ContainerBuilder();
    $class = new BarClass();
    $this->setExpectedException(\InvalidArgumentException::class, 'Service ID names must be lowercase: Bar');
    $container->set('Bar', $class);
  }

  /**
   * @covers ::setParameter
   */
  public function testSetParameterException() {
    $container = new ContainerBuilder();
    $this->setExpectedException(\InvalidArgumentException::class, 'Parameter names must be lowercase: Buzz');
    $container->setParameter('Buzz', 'buzz');
  }

  /**
   * @covers ::register
   */
  public function testRegisterException() {
    $container = new ContainerBuilder();
    $this->setExpectedException(\InvalidArgumentException::class, 'Service ID names must be lowercase: Bar');
    $container->register('Bar');
  }

  /**
   * @covers ::register
   */
  public function testRegister() {
    $container = new ContainerBuilder();
    $service = $container->register('bar');
    $this->assertTrue($service->isPublic());
  }

  /**
   * @covers ::setDefinition
   */
  public function testSetDefinition() {
    // Test a service with defaults.
    $container = new ContainerBuilder();
    $definition = new Definition();
    $service = $container->setDefinition('foo', $definition);
    $this->assertTrue($service->isPublic());
    $this->assertFalse($service->isPrivate());

    // Test a service with public set to false.
    $definition = new Definition();
    $definition->setPublic(FALSE);
    $service = $container->setDefinition('foo', $definition);
    $this->assertFalse($service->isPublic());
    $this->assertFalse($service->isPrivate());

    // Test a service with private set to true. Drupal does not support this.
    // We only support using setPublic() to make things not available outside
    // the container.
    $definition = new Definition();
    $definition->setPrivate(TRUE);
    $service = $container->setDefinition('foo', $definition);
    $this->assertTrue($service->isPublic());
    $this->assertFalse($service->isPrivate());
  }

  /**
   * @covers ::setAlias
   */
  public function testSetAlias() {
    $container = new ContainerBuilder();
    $container->register('bar');
    $alias = $container->setAlias('foo', 'bar');
    $this->assertTrue($alias->isPublic());
  }

  /**
   * Tests serialization.
   */
  public function testSerialize() {
    $container = new ContainerBuilder();
    $this->setExpectedException(\AssertionError::class);
    serialize($container);
  }

  /**
   * Tests constructor and resource tracking disabling.
   *
   * This test runs in a separate process to ensure the aliased class does not
   * affect any other tests.
   *
   * @runInSeparateProcess
   * @preserveGlobalState disabled
   */
  public function testConstructor() {
    class_alias(testInterface::class, 'Symfony\Component\Config\Resource\ResourceInterface');
    $container = new ContainerBuilder();
    $this->assertFalse($container->isTrackingResources());
  }

}

/**
 * A test interface for testing ContainerBuilder::__construct().
 *
 * @see \Drupal\Tests\Core\DependencyInjection\ContainerBuilderTest::testConstructor()
 */
interface testInterface {
}
