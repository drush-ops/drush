<?php
namespace Drush\Commands\help;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;

class HelpCommands extends DrushCommands {

  /**
   * Display usage details for a command.
   *
   * @command help
   * @param $name A command name
   * @usage drush help pm-uninstall
   *   Show help for a command.
   * @usage drush help pmu
   *   Show help for a command using an alias.
   * @usage drush help --format=xml
   *   Show all available commands in XML format.
   * @usage drush help --format=json
   *   All available commands, in JSON format.
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   * @topics docs-readme
   *
   * @return \Consolidation\AnnotatedCommand\Help\HelpDocument
   */
  public function help($name, $options = ['format' => 'helpcli']) {
    $application = \Drush::getApplication();
    $command = $application->get($name);
    $command->optionsHook();
    $helpDocument = new DrushHelpDocument($command);

    // This serves as example about how a command can add a custom Formatter.
    $formatter = new HelpCLIFormatter();
    $formatterManager = \Drush::getContainer()->get('formatterManager');
    $formatterManager->addFormatter('helpcli', $formatter);

    return $helpDocument;
  }

  /**
   * @hook validate help
   */
  public function validate(CommandData $commandData) {
    $name = $commandData->input()->getArgument('name');
    $application = \Drush::getApplication();
    annotation_adapter_add_legacy_commands_to_application($application);
    if (!in_array($name, array_keys($application->all()))) {
      throw new \Exception(dt("!name command not found.", array('!name' => $name)));
    }
  }
}
