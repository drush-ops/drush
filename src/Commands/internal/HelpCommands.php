<?php
namespace Drush\Commands\internal;

use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

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
   */
  public function help($name) {
    /** @var Application $application */
    $application = \Drush::getContainer()->get('application');
    $command = $application->find($name);
    $def = $command->getDefinition();
    $table = new Table($this->output());


    if ($usages = []) {
      $table->addRow([new TableCell('Usage:', array('colspan' => 2))]);
      // @todo oddly, $usage is not the name => value pair.
      foreach ($usages as $usage) {

      }
    }

    if ($arguments = $def->getArguments()) {
      $table->addRow([new TableCell('Arguments:', array('colspan' => 2))]);
      foreach ($arguments as $argument) {
        $table->addRow([$argument->getName(), $argument->getDescription()]);
      }
    }

    if ($options = $def->getOptions()) {
      $table->addRow([new TableCell('Options:', array('colspan' => 2))]);
      foreach ($options as $option) {
        $table->addRow([$option->getName(), $option->getDescription()]);
      }
    }

    $table->addRow([]);
    $table->addRow([new TableCell('Help:', array('colspan' => 2))]);
    $table->render([new TableCell('  '. $def->getSynopsis(), array('colspan' => 2))]);
  }
}
