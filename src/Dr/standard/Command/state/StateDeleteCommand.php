<?php

declare(strict_types=1);

namespace Drush\Dr\standard\Command\state;

use Drupal\Core\State\StateInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: 'Delete a state entry.',
    aliases: ['sdel', 'state-delete'],
    usages: ['state:delete system.cron_last'],
)]
final class StateDeleteCommand
{
    use AutowireTrait;
    use StateTrait;

    public const string NAME = 'state:delete';

    public function __construct(
        protected StateInterface $state,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('The state key, for example system.cron_last.')]
        string $key,
    ): int {
        $this->state->delete($key);

        $io = new SymfonyStyle($input, $output);
        $io->success((string) $this->t('Deleted state entry @key', ['@key' => $key]));

        return Command::SUCCESS;
    }
}
