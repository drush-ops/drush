<?php

namespace Custom\Library\AutowireTestClasses;

use Drush\Commands\AutowireTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AutowireTest
{
    use AutowireTrait;

    public function __construct(
        #[Autowire('a string as it is')]
        public readonly string $argListPlainValue,
        #[Autowire(null, 'autowire_test')]
        public readonly AutowireTestService $argListContainerService,
        #[Autowire(null, null, null, null, 'foo')]
        public readonly string $argListContainerParam,
        #[Autowire(value: 'a string as it is')]
        public readonly string $namedArgPlainValue,
        #[Autowire(service: 'autowire_test')]
        public readonly AutowireTestService $namedArgContainerService,
        #[Autowire(param: 'foo')]
        public readonly string $namedArgContainerParam,
        public readonly AutowireTestServiceInterface $noAutowireAttributeContainerService,
    ) {
    }
}
