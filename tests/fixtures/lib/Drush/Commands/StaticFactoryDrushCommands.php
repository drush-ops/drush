<?php

namespace Custom\Library\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DrupalKernel;

class StaticFactoryDrushCommands extends DrushCommands
{
    protected DrupalKernel $kernel;

    protected function __construct(DrupalKernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public static function create(ContainerInterface $container)
    {
        return new static($container->get('kernel'));
    }

    #[CLI\Command(name: 'site:path')]
    #[CLI\Help(description: "This command asks the Kernel for the site path.", hidden: true)]
    public function mySitePath()
    {
        $this->io()->text('The site path is: ' . $this->kernel->getSitePath());
    }
}
