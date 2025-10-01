<?php

declare(strict_types=1);

namespace Drush\Commands\core\deploy;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Psr\Log\LoggerInterface;
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
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
final class DeployHookMarkCompleteCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use DeployTrait;

    public const NAME = 'deploy:mark-complete';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $pending = $this->getRegistry()->getPendingUpdateFunctions();
        $this->getRegistry()->registerInvokedUpdates($pending);

        $this->logger->notice(sprintf('Marked %d pending deploy hooks as complete.', count($pending)));

        return new PropertyList(['result' => sprintf('Marked %d deploy hooks as complete', count($pending))]);
    }
}
