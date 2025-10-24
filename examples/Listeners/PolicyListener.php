<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Event\ConsoleDefinitionsEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 *  To temporarily use this Listener, use the --include option - e.g. `drush --include=/path/to/drush/examples updatedb`
 */
#[AsEventListener(method: 'onDefinitions')]
#[AsEventListener(method: 'onSqlSyncConsoleCommand')]
#[AsEventListener(method: 'onRSyncConsoleCommand')]
#[AsEventListener(method: 'onUpdateDbCommand')]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class PolicyListener
{
    use \Drush\Commands\AutowireTrait;

    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    public function onSqlSyncConsoleCommand(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()->getName() == 'sql:sync') {
            $input = $event->getInput();
            if ($input->getArgument('target') == '@prod') {
                $this->logger->error('Per {file}, you may never overwrite the production database.', ['file' => __FILE__]);
                $event->disableCommand();
            }
        }
    }

    public function onRSyncConsoleCommand(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()->getName() == 'rsync') {
            if (preg_match("/^@prod/", $event->getInput()->getArgument('target'))) {
                $this->logger->error('Per {file}, you may never rsync to the production site.', ['file' => __FILE__]);
                $event->disableCommand();
            }
        }
    }

    public function onUpdateDbCommand(ConsoleCommandEvent $event): void
    {
        if ($event->getCommand()->getName() == 'updatedb') {
            if (!$event->getInput()->getOption('secret') == 'mysecret') {
                $this->logger->error('UpdateDb command requires a secret token per site policy.');
                $event->disableCommand();
            }
        }
    }

    public function onDefinitions(ConsoleDefinitionsEvent $event): void
    {
        $event->getApplication()->get('updatedb')
            ->addOption(name: 'secret', mode: InputOption::VALUE_REQUIRED, description: 'A required token else user may not run updatedb command.');
    }
}
