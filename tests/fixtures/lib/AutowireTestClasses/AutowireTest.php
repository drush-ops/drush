<?php

namespace Custom\Library\AutowireTestClasses;

use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AutowireTest extends DrushCommands
{
    use AutowireTrait;

    public function __construct(
        #[Autowire('a string as it is')]
        public readonly string $argListStringValue,
        #[Autowire(null, 'autowire_test')]
        public readonly AutowireTestService $argListContainerService,
        #[Autowire(null, null, null, null, 'foo')]
        public readonly string $argListContainerParam,
        #[Autowire(value: 'a string as it is')]
        public readonly string $namedArgStringValue,
        #[Autowire(service: 'autowire_test')]
        public readonly AutowireTestService $namedArgContainerService,
        #[Autowire(param: 'foo')]
        public readonly string $namedArgContainerParam,
        public readonly AutowireTestServiceInterface $noAutowireAttributeContainerService,
    ) {
        parent::__construct();
    }
}
