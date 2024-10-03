<?php

namespace Unish;

use Custom\Library\AutowireTestClasses\AutowireTest;
use Custom\Library\AutowireTestClasses\AutowireTestService;
use Custom\Library\AutowireTestClasses\AutowireTestServiceInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drush\Commands\AutowireTrait;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @covers \Drush\Commands\AutowireTrait::create
 * @group base
 */
class AutowireArgumentsTest extends TestCase
{
    public function testDrupalContainer(): void
    {
        $drupalContainer = new ContainerBuilder();
        $drupalContainer->register('autowire_test', AutowireTestService::class);
        $drupalContainer->setAlias(AutowireTestServiceInterface::class, new Reference('autowire_test'));
        $drupalContainer->setParameter('foo', 'bar');
        $drupalContainer->compile();
        $drushContainer = new Container();
        $drushContainer->delegate($drupalContainer);

        $instance = AutowireTest::create($drupalContainer, $drushContainer);
        $this->assertInstanceOf(AutowireTest::class, $instance);

        $this->assertSame('a string as it is', $instance->argListStringValue);
        $this->assertSame('Hello World!', $instance->argListContainerService->greeting());
        $this->assertSame('bar', $instance->argListContainerParam);
        $this->assertSame('a string as it is', $instance->namedArgStringValue);
        $this->assertSame('Hello World!', $instance->namedArgContainerService->greeting());
        $this->assertSame('bar', $instance->namedArgContainerParam);
        $this->assertSame('Hello World!', $instance->noAutowireAttributeContainerService->greeting());
    }

    public function testDrushContainer(): void
    {
        $drushContainer = new Container();
        $drushContainer->add('autowire_test', AutowireTestService::class);
        $drushContainer->add(AutowireTestServiceInterface::class, AutowireTestService::class);

        $instance = AutowireTest::create($drushContainer);
        $this->assertInstanceOf(AutowireTest::class, $instance);

        $this->assertSame('a string as it is', $instance->argListStringValue);
        $this->assertSame('Hello World!', $instance->argListContainerService->greeting());
        // Drush container has no Drupal container as delegate. It can't resolve the container param.
        $this->assertSame('%foo%', $instance->argListContainerParam);
        $this->assertSame('a string as it is', $instance->namedArgStringValue);
        $this->assertSame('Hello World!', $instance->namedArgContainerService->greeting());
        // Drush container has no Drupal container as delegate. It can't resolve the container param.
        $this->assertSame('%foo%', $instance->namedArgContainerParam);
        $this->assertSame('Hello World!', $instance->noAutowireAttributeContainerService->greeting());
    }
}
