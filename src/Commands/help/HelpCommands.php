<?php
namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;

class HelpCommands extends DrushCommands
{

    /**
     * Display usage details for a command.
     *
     * @command help
     * @param $command_name A command name
     * @usage drush help pm-uninstall
     *   Show help for a command.
     * @usage drush help pmu
     *   Show help for a command using an alias.
     * @usage drush help --format=xml
     *   Show all available commands in XML format.
     * @usage drush help --format=json
     *   All available commands, in JSON format.
     * @bootstrap max
     * @topics docs:readme
     *
     * @return \Consolidation\AnnotatedCommand\Help\HelpDocument
     */
    public function help($command_name, $options = ['format' => 'helpcli', 'include-field-labels' => false, 'table-style' => 'compact'])
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

    /**
     * @hook validate help
     */
    public function validate(CommandData $commandData)
    {
        $name = $commandData->input()->getArgument('command_name');
        if (empty($name)) {
            throw new \Exception(dt("The help command requires that a command name be provided. Run `drush list` to see a list of available commands."));
        } else {
            $application = Drush::getApplication();
            if (!in_array($name, array_keys($application->all()))) {
                throw new \Exception(dt("!name command not found.", ['!name' => $name]));
            }
        }
    }
}
