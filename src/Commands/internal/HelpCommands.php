<?php
namespace Drush\Commands\internal;

use Drush\Commands\core\TopicCommands;
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
   * @bootstrap DRUSH_BOOTSTRAP_MAX
   */
  public function help($name) {
    /** @var Application $application */
    $application = \Drush::getContainer()->get('application');
    $command = $application->find($name);
    $def = $command->getDefinition();
    $table = new Table($this->output());
    $table->setStyle('compact');
    // @todo How to do this in Annotated?
    $allTopics = TopicCommands::getAllTopics();

    // How best to output this?
    drush_print($command->getDescription());

    if ($usages = $command->getExampleUsages()) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Examples:', array('colspan' => 2))]);
      foreach ($usages as $key => $description) {
        $table->addRow(['  ' . $key, $description]);
      }
    }

    if ($arguments = $def->getArguments()) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Arguments:', array('colspan' => 2))]);
      foreach ($arguments as $argument) {
        $formatted = $this->formatArgumentName($argument);
        $description = $argument->getDescription();
        if ($argument->getDefault()) {
          $description .= ' [default: ' . $argument->getDefault() . ']';
        }
        $table->addRow(['  ' . $formatted, $description]);
      }
    }

    if ($aliases = $def->getOptions()) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Options:', array('colspan' => 2))]);
      foreach ($aliases as $option) {
        $formatted = $this->formatOption($option);
        $table->addRow(['  ' . $formatted, $option->getDescription()]);
      }
    }

    if ($topics = $command->getTopics()) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Topics:', array('colspan' => 2))]);
      foreach ($topics as $topic) {
        // @todo deal with long descriptions
        $table->addRow(['  ' . $topic, substr($allTopics[$topic]['description'], 0, 30)]);
      }
    }

    if ($aliases = $command->getAliases()) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Aliases: '. implode(' ', $aliases), array('colspan' => 2))]);
    }

    $table->render();
  }

  function formatOption($option) {
    $value = '';
    if ($option->acceptValue()) {
      $value = sprintf(
        ' %s%s%s',
        $option->isValueOptional() ? '[' : '',
        strtoupper($option->getName()),
        $option->isValueOptional() ? ']' : ''
      );
    }

    $shortcut = $option->getShortcut() ? sprintf('-%s|', $option->getShortcut()) : '';
    return sprintf('[%s--%s%s]', $shortcut, $option->getName(), $value);
  }

  function formatArgumentName($argument) {
    $element = $argument->getName();
    if (!$argument->isRequired()) {
      $element = '['.$element.']';
    } elseif ($argument->isArray()) {
      $element = $element.' ('.$element.')';
    }

    if ($argument->isArray()) {
      $element .= '...';
    }

    return $element;
  }
}
