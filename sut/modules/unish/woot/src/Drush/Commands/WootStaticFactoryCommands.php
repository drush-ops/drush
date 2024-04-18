<?php

declare(strict_types=1);

namespace Drupal\woot\Drush\Commands;

use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;

/**
 * Module commandfiles discovered by virtue of being located in the
 * Drush/Commands directory + namespace, relative to the module
 * the commandfile appears in.
 *
 * The static 'create' function (the static factory) is used to
 * initialize the command instance, similar to the pattern used
 * by Drupal forms.
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
     */
    public function wootFactory($count = 10)
    {
        $a = 1;
        $b = 1;

        $siteName = $this->configFactory->get('system.site')->get('name');
        $this->io()->writeln('Woot factorial command with a static factory method in site ' . $siteName);

        foreach (range(1, $count) as $i) {
            $this->io()->writeln("Woot " . $a);
            $t = $a + $b;
            $a = $b;
            $b = $t;
        }
    }
}
