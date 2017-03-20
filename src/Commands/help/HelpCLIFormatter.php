<?php
namespace Drush\Commands\help;

use Consolidation\OutputFormatters\Formatters\FormatterInterface;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;

/**
 * Format an array into CLI help string.
 */
class HelpCLIFormatter implements FormatterInterface
{

  /**
   * @inheritdoc
   */
  public function write(OutputInterface $output, $data, FormatterOptions $options)
  {
    $table = new Table($output);
    $table->setStyle('compact');

    $output->writeln($data['description']);

    if (array_key_exists('examples', $data)) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Examples:', array('colspan' => 2))]);
      foreach ($data['examples'] as $example) {
        $table->addRow(['  ' . $example['usage'], $example['description']]);
      }
    }

    if (array_key_exists('arguments', $data)) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Arguments:', array('colspan' => 2))]);
      foreach ($data['arguments'] as $argument) {
        $formatted = $this->formatArgumentName($argument);
        $description = $argument['description'];
        // @todo No argument default in Helpdocument
        //        if ($argument['default']) {
//          $description .= ' [default: ' . $argument->getDefault() . ']';
//        }
        $table->addRow(['  ' . $formatted, $description]);
      }
    }

    if (array_key_exists('options', $data)) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Options:', array('colspan' => 2))]);
      foreach ($data['options'] as $option) {
        $formatted = $this->formatOption($option);
        $defaults = array_key_exists('defaults', $option) ? ' [default: "' . implode(' ', $option['defaults']) . '"]' : '';
        $table->addRow(['  ' . $formatted, $option['description'] . $defaults]);
      }
    }

    if (array_key_exists('topics', $data)) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Topics:', array('colspan' => 2))]);
      foreach ($data['topics'] as $topic) {
        $topic_command = \Drush::getApplication()->find($topic);
        $table->addRow(['  ' . $topic, $topic_command->getDescription()]);
      }
    }

    // @todo Fix this variability in key name upstream.
    if ($aliases = array_key_exists('aliases', $data) ? $data['aliases'] :  array_key_exists('alias', $data) ? [$data['alias']] : []) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Aliases: '. implode(', ', $aliases), array('colspan' => 2))]);
    }

    $table->render();
  }

  public function formatOption($option) {
    // Remove leading dashes.
    $option['name'] = substr($option['name'], 2);

    $value = '';
    if ($option['accept_value']) {
      $value = '='.strtoupper($option['name']);

      if (!$option['is_value_required']) {
        $value = '['.$value.']';
      }
    }

    $synopsis = sprintf('%s%s',
      $option['shortcut']  ? sprintf('-%s, ', $option['shortcut'] ) : '    ',
      sprintf('--%s%s', $option['name'], $value)
    );
    return $synopsis;
  }

  public function formatArgumentName($argument) {
    $element = $argument['name'];
    if (!$argument['is_required']) {
      $element = '['.$element.']';
    } elseif ($argument['is_array']) {
      $element = $element.' ('.$element.')';
    }

    if ($argument['is_array']) {
      $element .= '...';
    }

    return $element;
  }
}
