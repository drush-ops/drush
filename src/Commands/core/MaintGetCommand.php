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
    description: 'Get maintenance mode. Returns 1 if enabled, 0 if not.',
    aliases: ['mget'],
)]
#[CLI\Version(version: '11.5')]
final class MaintGetCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'maint:get';

    public function __construct(
        protected readonly StateInterface $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addUsage('maint:get')
            ->setHelp('Print value of maintenance mode in Drupal. Consider using maint:status instead when chaining commands.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $this->state->get('system.maintenance_mode');
        $output->writeln($value ? '1' : '0');
        return self::SUCCESS;
    }
}
