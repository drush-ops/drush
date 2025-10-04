<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Fail if maintenance mode is enabled.',
    aliases: ['mstatus'],
)]
#[CLI\Version(version: '11.5')]
final class MaintStatusCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'maint:status';

    public function __construct(
        protected readonly StateInterface $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addUsage('maint:status && drush cron')
            ->setHelp('This commands fails with exit code of 3 when maintenance mode is on. This special exit code distinguishes from a failure to complete. Only run cron when Drupal is not in maintenance mode.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $this->state->get('system.maintenance_mode');
        return $value ? 3 : self::SUCCESS;
    }
}
