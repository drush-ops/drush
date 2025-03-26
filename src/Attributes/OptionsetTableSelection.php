<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\DrushCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetTableSelection
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addOption('skip-tables-key', 'A key in the $skip_tables array. @see [Drush config](../using-drush-configuration.md)', [], DrushCommands::REQ);
        $commandInfo->addOption('structure-tables-key', 'A key in the $structure_tables array. @see [Drush config](../using-drush-configuration.md)', [], DrushCommands::REQ);
        $commandInfo->addOption('tables-key', 'A key in the $tables array.', [], DrushCommands::REQ);
        $commandInfo->addOption('skip-tables-list', 'A comma-separated list of tables to exclude completely.', [], DrushCommands::REQ);
        $commandInfo->addOption('structure-tables-list', 'A comma-separated list of tables to include for structure, but not data.', [], DrushCommands::REQ);
        $commandInfo->addOption('skip-tables-list', 'A comma-separated list of tables to exclude completely.', [], DrushCommands::REQ);
        $commandInfo->addOption('tables-list', 'A comma-separated list of tables to transfer.', [], DrushCommands::REQ);
    }
}
