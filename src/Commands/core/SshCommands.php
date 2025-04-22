<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\Util\Shell;
use Consolidation\SiteProcess\Util\Tty;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class SshCommands extends DrushCommands implements StdinAwareInterface
{
    use AutowireTrait;
    use StdinAwareTrait;

    const SSH = 'site:ssh';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Connect to a webserver via SSH, and optionally run a shell command.
     */
    #[CLI\Command(name: self::SSH, aliases: ['ssh', 'site-ssh'])]
    #[CLI\Argument(name: 'code', description: 'Code which should run at remote host.')]
    #[CLI\Option(name: 'cd', description: 'Directory to change to. Defaults to Drupal root.')]
    #[CLI\OptionsetProcBuild]
    #[CLI\HandleRemoteCommands]
    #[CLI\Usage(name: 'drush @mysite ssh', description: 'Open an interactive shell on @mysite\'s server.')]
    #[CLI\Usage(name: 'drush @prod ssh "ls /tmp"', description: 'Run <info>ls /tmp</info> on <info>@prod</info> site.')]
    #[CLI\Usage(name: 'drush @prod ssh "git pull"', description: 'Run <info>git pull</info> on the Drupal root directory on the <info>@prod</info> site.')]
    #[CLI\Usage(name: 'drush ssh "git pull"', description: 'Run <info>git pull</info> on the local Drupal root directory.')]
    #[CLI\Usage(name: 'echo \'Deny from all\' | drush @prod ssh "tee > do-not-expose-dir/.htaccess"', description: 'Protect directory <info>do-not-expose-dir</info> from being served by Apache httpd.')]
    #[CLI\Topics(topics: [DocsCommands::ALIASES])]
    public function ssh(array $code, $options = ['cd' => self::REQ]): void
    {
        $alias = $this->siteAliasManager->getSelf();

        if ($code === []) {
            $code[] = 'bash';
            $code[] = '-l';

            // We're calling an interactive 'bash' shell, so we want to
            // force tty to true.
            $options['tty'] = true;
        }

        if ((count($code) == 1)) {
            $code = [Shell::preEscaped($code[0])];
        }

        $process = $this->processManager()->siteProcess($alias, $code);
        if (!Tty::isTtySupported()) {
            $process->setInput($this->stdin()->getStream());
        } else {
            $process->setTty($options['tty']);
        }
        // The transport handles the chdir during processArgs().
        $fallback = $alias->hasRoot() ? $alias->root() : null;
        $process->setWorkingDirectory($options['cd'] ?: $fallback);
        $process->mustRun($process->showRealtime());
    }
}
