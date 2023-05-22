<?php

namespace Custom\Library\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DrupalKernel;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Composer library commandfiles discovered by virtue of being located
 * in Drush/Commands directory + namespace, relative to some entry in
 * the library's `autoload` section in its composer.json file.
 *
 * The static 'create' function (the static factory) is used to
 * initialize the command instance, similar to the pattern used
 * by Drupal forms.
 */
class StaticFactoryDrushCommands extends DrushCommands
{
    protected DrupalKernel $kernel;

    protected function __construct(DrupalKernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public static function create(ContainerInterface $container): self
    {
        return new static($container->get('kernel'));
    }

    #[CLI\Command(name: 'site:path')]
    #[CLI\Help(description: "This command asks the Kernel for the site path.", hidden: true)]
    public function mySitePath(SymfonyStyle $io)
    {
        $io->text('The site path is: ' . $this->kernel->getSitePath());
    }
}
