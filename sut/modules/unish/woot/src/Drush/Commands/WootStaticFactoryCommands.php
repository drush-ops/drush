<?php

declare(strict_types=1);

namespace Drupal\woot\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Commandfiles must be listed in a module's drush.services.yml file.
 */
class WootStaticFactoryCommands extends DrushCommands
{
    protected $configFactory;

    protected function __construct($configFactory)
    {
        $this->configFactory = $configFactory;
    }

    public static function create(ContainerInterface $container): self
    {
        return new static($container->get('config.factory'));
    }

    /**
     * Woot factory-aly.
     *
     * @command woot-factory
     * @aliases wt
     */
    public function woot($count = 10)
    {
        $a = 1;
        $b = 1;

        $siteName = $this->configFactory->get('system.site')->get('name');
        $this->io()->writeln('Woot factorial command with a static factory method in site ' . $siteName);

        foreach (range(1,$count) as $i) {
            $this->io()->writeln("Woot " . $a);
            $t = $a + $b;
            $a = $b;
            $b = $t;
        }
    }
}
