<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Attributes\OptionsetTableSelection;
use Drush\Boot\DrupalBootLevels;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
class OptionsetTableSelectionListener
{
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflection = new \ReflectionObject($code);
            $attributes = $reflection->getAttributes(OptionsetTableSelection::class);
            if (empty($attributes)) {
                continue;
            }
            $command->addOption('skip-tables-key', '', InputOption::VALUE_REQUIRED, 'A key in the $skip_tables array. @see [Site aliases](../site-aliases.md)');
            $command->addOption('structure-tables-key', '', InputOption::VALUE_REQUIRED, 'A key in the $structure_tables array. @see [Site aliases](../site-aliases.md)');
            $command->addOption('tables-key', '', InputOption::VALUE_REQUIRED, 'A key in the $tables array.');
            $command->addOption('skip-tables-list', '', InputOption::VALUE_REQUIRED, 'A comma-separated list of tables to exclude completely.');
            $command->addOption('structure-tables-list', '', InputOption::VALUE_REQUIRED, 'A comma-separated list of tables to include for structure, but not data.');
            $command->addOption('tables-list', '', InputOption::VALUE_REQUIRED, 'A comma-separated list of tables to transfer.', []);
        }
    }
}
