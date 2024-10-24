<?php

namespace Drush\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class OptionSets
{
    public static function sql(Command $command): void
    {
        $command->addOption('database', '', InputOption::VALUE_REQUIRED, 'The DB connection key if using multiple connections in settings.php.', 'default');
        $command->addOption('db-url', '', InputOption::VALUE_REQUIRED, 'A Drupal 6 style database URL. For example <info>mysql://root:pass@localhost:port/dbname</info>');
        $command->addOption('target', '', InputOption::VALUE_REQUIRED, 'The name of a target within the specified database connection.', 'default');
        $command->addOption('show-passwords', '', InputOption::VALUE_NONE, 'Show password on the CLI. Useful for debugging.');
    }
}
