<?php

declare(strict_types=1);

namespace Drush\Commands\state;

use Drupal\Core\State\StateInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Delete a state entry.',
    aliases: ['sdel', 'state-delete']
)]
final class StateDeleteCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'state:delete';

    public function __construct(
        protected StateInterface $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The state key, for example <info>system.cron_last</info>.')
            ->addUsage('state:del system.cron_last');

        $this->setHelp('Delete a state entry. Example: Delete state entry for system.cron_last.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->state->delete($input->getArgument('key'));

        return self::SUCCESS;
    }
}
