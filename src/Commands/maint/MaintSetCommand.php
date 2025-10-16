<?php

declare(strict_types=1);

namespace Drush\Commands\maint;

use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Set maintenance mode.',
    aliases: ['mset'],
)]
#[CLI\Version(version: '11.5')]
final class MaintSetCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'maint:set';

    public function __construct(
        protected readonly StateInterface $state
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('value', InputArgument::REQUIRED, 'The value to assign to the state key (0 or 1)')
            ->addUsage('maint:set 1')
            ->addUsage('maint:set 0')
            ->setHelp('Put site into Maintenance mode with value 1, or remove site from Maintenance mode with value 0.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $value = $input->getArgument('value');
        $this->state->set('system.maintenance_mode', (bool) $value);
        return self::SUCCESS;
    }
}
