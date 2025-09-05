<?php

namespace Custom\Library;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drush\Commands\DrushCommands;
use Psr\Log\LoggerInterface;

final class CreateFactoryExpectsDrupalContainer extends DrushCommands
{
    public function __construct(
        public readonly string $string,
        public readonly RedirectDestinationInterface $redirectDestination,
    ) {
        parent::__construct();
    }

    public static function create(ContainerInterface $container): self
    {
        return new self('a string as it is', $container->get('redirect.destination'));
    }
}
