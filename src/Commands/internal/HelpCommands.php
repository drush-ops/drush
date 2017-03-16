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
   * @topics docs-readme
   *
   * @return \DOMDocument
   */
  public function help($name, $options = ['format' => 'xml']) {
    /** @var Application $application */
    $application = \Drush::getContainer()->get('application');
    $command = $application->find($name);
    $def = $command->getDefinition();
    // @todo How to do this in Annotated?
    $allTopics = TopicCommands::getAllTopics();

    // "Create" the document.
    $xml = new \DOMDocument( "1.0", "UTF-8" );

    $xml->appendChild($xml->createElement('help', $command->getProcessedHelp()));
    // @todo, upgrade Annotated to recognize these as different.
    $xml->appendChild($xml->createElement('description', $command->getProcessedHelp()));

    $xml_examples = $xml->createElement('examples');
    if ($usages = $command->getExampleUsages()) {
      foreach ($usages as $key => $description) {
        $element = $xml->createElement('example', $key);
        $element->setAttribute('description', $description);
        $xml_examples->appendChild($element);
      }
    }
    $xml->appendChild($xml_examples);

    $xml_arguments = $xml->createElement('arguments');
    if ($arguments = $def->getArguments()) {
      foreach ($arguments as $argument) {
        $element = $xml->createElement('argument');
        $element->setAttribute('isRequired', $argument->isRequired());
        $element->setAttribute('isArray', $argument->isArray());
        $element->setAttribute('name', $argument->getName());
        $xml_description = $xml->createElement('description', $argument->getDescription());
        $element->appendChild($xml_description);
        $xml_defaults = $xml->createElement('defaults');
        if ($argument->getDefault()) {
          $xml_defaults->appendChild($xml->createElement('default', $argument->getDefault()));
        }
        $element->appendChild($xml_defaults);
        $xml_arguments->appendChild($element);
      }
    }
    $xml->appendChild($xml_arguments);

    $xml_options = $xml->createElement('options');
    if ($options = $def->getOptions()) {
      foreach ($options as $option) {
        $element = $xml->createElement('option');
        $element->setAttribute('name', '=' . $option->getName()); // Console XML has a leading =.
        $element->setAttribute('accept_value', $option->acceptValue());
        $element->setAttribute('is_value_required', $option->isValueRequired());
        $element->setAttribute('shortcut', $option->getShortcut());
        $element->setAttribute('is_multiple', 0); // @todo
        // $element->setAttribute('default', $option->getDefault());
        $element->appendChild($xml->createElement('description', $option->getDescription()));
        $xml_options->appendChild($element);
      }
    }
    $xml->appendChild($xml_options);

    $xml_topics = $xml->createElement('topics');
    if ($topics = $command->getTopics()) {
      foreach ($topics as $topic) {
        $element = $xml->createElement('topic');
        $element->appendChild($xml->createElement('name', $topic));
        $element->appendChild($xml->createElement('description', $allTopics[$topic]['description']));
        $xml_topics->appendChild($element);
      }
    }
    $xml->appendChild($xml_topics);

    $xml_aliases = $xml->createElement('aliases');
    if ($aliases = $command->getAliases()) {
      foreach ($aliases as $alias) {
        $element = $xml->createElement('alias', $alias);
        $xml_aliases->appendChild($element);
      }
    }
    $xml->appendChild($xml_aliases);

    return $xml;


    // Everything below will move to a custom output formatter.
    $table = new Table($this->output());
    $table->setStyle('compact');

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

    if ($options = $def->getOptions()) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Options:', array('colspan' => 2))]);
      foreach ($options as $option) {
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
