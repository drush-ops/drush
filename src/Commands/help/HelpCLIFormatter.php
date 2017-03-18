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

    // @todo. Get input data as an array.
    $output->writeln($data['description']);

    if ($examples = $data['examples']) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Examples:', array('colspan' => 2))]);
      foreach ($examples as $example) {
        $table->addRow(['  ' . $example['usage'], $example['description']]);
      }
    }

    if ($arguments = $data['arguments']) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Arguments:', array('colspan' => 2))]);
      foreach ($arguments as $argument) {
        $formatted = $this->formatArgumentName($argument);
        $description = $argument['description'];
        // @todo No default in Helpdocument
        //        if ($argument['default']) {
//          $description .= ' [default: ' . $argument->getDefault() . ']';
//        }
        $table->addRow(['  ' . $formatted, $description]);
      }
    }

    if ($options = $data['options']) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Options:', array('colspan' => 2))]);
      foreach ($options as $option) {
        $formatted = $this->formatOption($option);
        $table->addRow(['  ' . $formatted, $option['description']]);
      }
    }

    if ($topics = $data['topics']) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Topics:', array('colspan' => 2))]);
      foreach ($topics as $topic) {
        $topic_command = $this->application->find($topic);
        $table->addRow(['  ' . $topic, substr('foo', 0, 30)]);
      }
    }

    // @todo
    if ($aliases = [$data['alias']]) {
      $table->addRow(['','']);
      $table->addRow([new TableCell('Aliases: '. implode(' ', $aliases), array('colspan' => 2))]);
    }

    $table->render();

    // $output->writeln($help);
  }

  public function formatOption($option) {
    $value = '';
    if ($option['accept_value']) {
      $value = sprintf(
        ' %s%s%s',
        $option['is_value_required'] ? '[' : '',
        strtoupper($option['name']),
        $option['is_value_required'] ? ']' : ''
      );
    }

    $shortcut = $option['shortcut'] ? sprintf('-%s|', $option['shortcut']) : '';
    return sprintf('[%s--%s%s]', $shortcut, $option['name'], $value);
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
