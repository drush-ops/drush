<?php

declare(strict_types=1);

namespace Custom\Library\Drush\Commands;

use Drupal\woot\AutowireTestService;
use Drupal\woot\AutowireTestServiceInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AutowireTestCommands extends DrushCommands
{
    use AutowireTrait;

    public function __construct(
        #[Autowire('a string as it is')]
        private readonly string $argListStringValue,
        #[Autowire(null, 'woot.autowire_test')]
        private readonly AutowireTestService $argListContainerService,
        #[Autowire(null, null, null, null, 'foo')]
        private readonly string $argListContainerParam,
        #[Autowire(value: 'a string as it is')]
        private readonly string $namedArgStringValue,
        #[Autowire(service: 'woot.autowire_test')]
        private readonly AutowireTestService $namedArgContainerService,
        #[Autowire(param: 'foo')]
        private readonly string $namedArgContainerParam,
        private readonly AutowireTestServiceInterface $noAutowireAttributeContainerService,
    ) {
        parent::__construct();
    }

    #[CLI\Command(name: 'test_autowire:drupal-container')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    #[CLI\Help(hidden: true)]
    public function drupal(): string
    {
        $values = [];
        $constructor = new \ReflectionMethod($this, '__construct');
        foreach ($constructor->getParameters() as $param) {
            $values[] = (string) $this->{$param->getName()};
        }
        return implode("\n", $values);
    }

    #[CLI\Command(name: 'test_autowire:drush-container')]
    #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
    #[CLI\Help(hidden: true)]
    public function drush(): string
    {
        $values = [];
        $constructor = new \ReflectionMethod($this, '__construct');
        foreach ($constructor->getParameters() as $param) {
            $values[] = (string) $this->{$param->getName()};
        }
        return implode("\n", $values);
    }
}
