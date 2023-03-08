<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\DrushCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetSql
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addOption('database', 'The DB connection key if using multiple connections in settings.php.', [], 'default');
        $commandInfo->addOption('db-url', 'A Drupal 6 style database URL. For example <info>mysql://root:pass@localhost:port/dbname</info>', [], DrushCommands::REQ);
        $commandInfo->addOption('target', 'The name of a target within the specified database connection.', [], 'default');
        $commandInfo->addOption('show-passwords', 'Show password on the CLI. Useful for debugging.', [], false);
    }
}
