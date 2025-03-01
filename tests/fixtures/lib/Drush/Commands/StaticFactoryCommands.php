<?php

namespace Custom\Library\Drush\Commands;

use Drupal\Core\DrupalKernelInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Composer library commandfiles discovered by virtue of being located
 * in Drush/Commands directory + namespace, relative to some entry in
 * the library's `autoload` section in its composer.json file.
 */
class StaticFactoryCommands extends DrushCommands
{
    use AutowireTrait;

    protected function __construct(protected DrupalKernelInterface $kernel)
    {
        parent::__construct();
    }

    #[CLI\Command(name: 'site:path')]
    #[CLI\Help(description: "This command asks the Kernel for the site path.", hidden: true)]
    public function mySitePath()
    {
        $this->io()->note('The site path is: ' . $this->kernel->getSitePath());
    }
}
