<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\CronInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Run all cron hooks in all active modules for the specified site.',
    aliases: ['cron', 'core-cron'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Cron])]
final class CronCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'core:cron';

    public function __construct(
        protected readonly CronInterface $cron,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Consider using `drush maint:status && drush core:cron` to avoid cache poisoning during maintenance mode.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->cron->run();
        return $result ? self::SUCCESS : self::FAILURE;
    }
}
