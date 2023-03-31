<?php

declare(strict_types=1);

namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;

class HelpCommands extends DrushCommands
{
    const HELP = 'help';

    /**
     * Display usage details for a command.
     */
    #[CLI\Command(name: self::HELP)]
    #[CLI\Argument(name: 'command_name', description: 'A command name')]
    #[CLI\Usage(name: 'drush help pm:uninstall', description: 'Show help for a command.')]
    #[CLI\Usage(name: 'drush help pmu', description: 'Show help for a command using an alias.')]
    #[CLI\Usage(name: 'drush help --format=xml', description: 'Show all available commands in XML format.')]
    #[CLI\Usage(name: 'drush help --format=json', description: 'All available commands, in JSON format.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX)]
    #[CLI\Topics(topics: [DocsCommands::README])]
    public function help($command_name = '', $options = ['format' => 'helpcli', 'include-field-labels' => false, 'table-style' => 'compact']): DrushHelpDocument
    {
        $application = Drush::getApplication();
        $command = $application->get($command_name);
        if ($command instanceof AnnotatedCommand) {
            $command->optionsHook();
        }
        $helpDocument = new DrushHelpDocument($command);

        // This serves as example about how a command can add a custom Formatter.
        $formatter = new HelpCLIFormatter();
        $formatterManager = Drush::getContainer()->get('formatterManager');
        $formatterManager->addFormatter('helpcli', $formatter);

        return $helpDocument;
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::HELP)]
    public function validate(CommandData $commandData): void
    {
        $name = $commandData->input()->getArgument('command_name');
        if (empty($name)) {
            throw new \Exception(dt("The help command requires that a command name be provided. Run `drush list` to see a list of available commands."));
        }
    }
}
