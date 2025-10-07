<?php

declare(strict_types=1);

namespace Drush\Commands\deploy;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Mark all deploy hooks as having run.',
)]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
#[CLI\Version(version: '10.6.1')]
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
final class DeployHookMarkCompleteCommand extends Command
{
    use DeployTrait;

    public const NAME = 'deploy:mark-complete';

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $pending = $this->getRegistry()->getPendingUpdateFunctions();
        $this->getRegistry()->registerInvokedUpdates($pending);

        (new DrushStyle($input, $output))->success(sprintf('Marked %d pending deploy hooks as complete.', count($pending)));
        return Command::SUCCESS;
    }
}
