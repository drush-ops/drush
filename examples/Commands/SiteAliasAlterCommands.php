<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Load this example by using the --include option - e.g. `drush --include=/path/to/drush/examples`
 */
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
class SiteAliasAlterCommands extends DrushCommands
{
    use AutowireTrait;

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * A few example alterations to site aliases.
     */
    #[CLI\Hook(type: HookManager::PRE_INITIALIZE, target: '*')]
    public function alter(InputInterface $input, AnnotationData $annotationData)
    {
        $self = $this->siteAliasManager->getSelf();
        if ($self->isRemote()) {
            // Always pass along ssh keys.
            if (!$self->has('ssh.options')) {
                // Don't edit the alias - edit the general config service instead.
                $this->getConfig()->set('ssh.options', '-o ForwardAgent=yes');
            }

            // Change the SSH user.
            $input->setOption('remote-user', 'mw2');

            // Test to see if specific environment really exists in wildcard
            // aliases, but only if the target is a specific host.
            $host = $self->get('host');
            if (preg_match('#\.myserver.com$#', $host)) {
                $ip = gethostbyname($host);
                // If the return value of gethostbyname equals its input parameter,
                // that indicates failure.
                if ($host == $ip) {
                    $aliasName = $self->name();
                    throw new \Exception("The alias $aliasName refers to an environment that does not exist.");
                }
            }
        }
    }
}
