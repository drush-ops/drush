<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
class OptionsetSqlListener
{
    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflection = new \ReflectionObject($code);
            $attributes = $reflection->getAttributes(CLI\OptionsetSql::class);
            if (empty($attributes)) {
                continue;
            }
            $command->addOption('database', '', InputOption::VALUE_REQUIRED, 'The DB connection key if using multiple connections in settings.php.', 'default');
            $command->addOption('db-url', '', InputOption::VALUE_REQUIRED, 'A Drupal 6 style database URL. For example <info>mysql://root:pass@localhost:port/dbname</info>');
            $command->addOption('target', '', InputOption::VALUE_REQUIRED, 'The name of a target within the specified database connection.', 'default');
            $command->addOption('show-passwords', '', InputOption::VALUE_NONE, 'Show password on the CLI. Useful for debugging.');
        }
    }
}
