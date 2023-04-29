<?php

namespace Custom\Library\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Attributes as DR;
use Consolidation\AnnotatedCommand\Attributes as AC;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StaticFactoryDrushCommands extends DrushCommands
{
    protected $kernel;

    protected function __construct(ContainerInterface $container)
    {
        $this->kernel = $container->get('kernel');
    }

    public static function create(ContainerInterface $container)
    {
        return new static($container);
    }

    #[CLI\Command(name: 'site:path', aliases: ['c'])]
    #[CLI\Help(description: "This command asks the Kernel for the site path.", hidden: true)]
    public function mySitePath()
    {
        $this->io()->text('The site path is: ' . $this->kernel->getSitePath());
    }
}
