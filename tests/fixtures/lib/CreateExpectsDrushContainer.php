<?php

namespace Custom\Library;

use Drush\Commands\DrushCommands;
use League\Container\DefinitionContainerInterface;
use Psr\Log\LoggerInterface;

final class CreateExpectsDrushContainer extends DrushCommands
{
    public function __construct(
        public readonly string $string,
        public readonly LoggerInterface $log,
    ) {
        parent::__construct();
    }

    public static function create(DefinitionContainerInterface $container): self
    {
        return new self('a string as it is', $container->get('logger'));
    }
}
