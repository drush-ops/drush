<?php

declare(strict_types=1);

namespace Unish;

use Drupal\woot\AutowireTestService;
use Drupal\woot\AutowireTestServiceInterface;
use Drush\Commands\pm\PmCommands;
use Drush\Drush;

/**
 * @covers \Drush\Commands\AutowireTrait::create
 * @group base
 */
class AutowireArgumentsTest extends UnishIntegrationTestCase
{
    public function testWithDrupalContainer(): void
    {
        $this->drush(PmCommands::INSTALL, ['woot']);

        $expected = [
            // Autowire('a string as it is')
            'a string as it is',
            // Autowire(null, 'woot.autowire_test')
            'Hello World!',
            // Autowire(null, null, null, null, 'foo')
            'bar',
            // Autowire(value: 'a string as it is')
            'a string as it is',
            // Autowire(service: 'woot.autowire_test')
            'Hello World!',
            // Autowire(param: 'foo')
            'bar',
            // Autowire by service full qualified interface name
            // @see \Drupal\woot\AutowireTestServiceInterface
            'Hello World!',
        ];

        $this->drush('test_autowire:drupal-container');
        $this->assertSame(implode("\n", $expected), $this->getOutput());

        $this->drush(PmCommands::UNINSTALL, ['woot']);
    }

    // @todo This test will fail because I coudn't find a way to add a new
    //   service to Drush container.
    public function testWithDrushContainer(): void
    {
        $drushContainer = Drush::getContainer();
        $drushContainer->add('woot.autowire_test', AutowireTestService::class);
        $drushContainer->add(AutowireTestServiceInterface::class, AutowireTestService::class);
        Drush::setContainer($drushContainer);

        $expected = [
            // Autowire('a string as it is')
          'a string as it is',
            // Autowire(null, 'woot.autowire_test')
          'Hello World!',
            // Autowire(null, null, null, null, 'foo')
          'bar',
            // Autowire(value: 'a string as it is')
          'a string as it is',
            // Autowire(service: 'woot.autowire_test')
          'Hello World!',
            // Autowire(param: 'foo')
          'bar',
            // Autowire by service full qualified interface name
            // @see \Drupal\woot\AutowireTestServiceInterface
          'Hello World!',
        ];

        $this->drush('test_autowire:drush-container');
        $this->assertSame(implode("\n", $expected), $output);
    }
}
