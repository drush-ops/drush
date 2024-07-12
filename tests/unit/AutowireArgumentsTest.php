<?php

namespace Unish;

use Custom\Library\AutowireTestClasses\AutowireTest;
use Custom\Library\AutowireTestClasses\AutowireTestService;
use Custom\Library\AutowireTestClasses\AutowireTestServiceInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drush\Commands\AutowireTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @group base
 */
class AutowireArgumentsTest extends TestCase
{
    /**
     * @covers \Drush\Commands\AutowireTrait::create
     */
    public function test()
    {
        $container = new ContainerBuilder();
        $container->register('autowire_test', AutowireTestService::class);
        $container->setAlias(AutowireTestServiceInterface::class, new Reference('autowire_test'));
        $container->setParameter('foo', 'bar');
        $container->compile();

        $instance = AutowireTest::create($container);
        $this->assertInstanceOf(AutowireTest::class, $instance);

        $this->assertSame('a string as it is', $instance->argListPlainValue);
        $this->assertSame('Hello World!', $instance->argListContainerService->greeting());
        $this->assertSame('bar', $instance->argListContainerParam);
        $this->assertSame('a string as it is', $instance->namedArgPlainValue);
        $this->assertSame('Hello World!', $instance->namedArgContainerService->greeting());
        $this->assertSame('bar', $instance->namedArgContainerParam);
        $this->assertSame('Hello World!', $instance->noAutowireAttributeContainerService->greeting());
    }
}
