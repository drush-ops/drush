<?php
namespace Drush\Commands\help;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Formatters\FormatterInterface;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Symfony\Component\Console\Output\OutputInterface;

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
    $formatterManager = new FormatterManager();

    $output->writeln($data['description']);
    if (array_key_exists('help', $data) && $data['help'] != $data['description']) {
      $output->writeln('');
      $output->writeln($data['help']);
    }

    if (array_key_exists('examples', $data)) {
      $output->writeln('');
      $output->writeln('Examples:');
      foreach ($data['examples'] as $example) {
        $rows[] = [' ' . $example['usage'], $example['description']];
      }
      $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
    }

    if (array_key_exists('arguments', $data)) {
      $rows = [];
      $output->writeln('');
      $output->writeln('Arguments:');
      foreach ($data['arguments'] as $argument) {
        $formatted = $this->formatArgumentName($argument);
        $description = $argument['description'];
        // @todo No argument default in Helpdocument
        //        if ($argument['default']) {
//          $description .= ' [default: ' . $argument->getDefault() . ']';
//        }
        $rows[] = [' ' . $formatted, $description];
      }
      $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
    }

    if (array_key_exists('options', $data)) {
      $rows = [];
      $output->writeln('');
      $output->writeln('Options:');
      foreach ($data['options'] as $option) {
        if (substr($option['name'], 0, 8) !== '--notify' && substr($option['name'], 0, 5) !== '--xh-' && substr($option['name'], 0, 11) !== '--druplicon') {
          $rows[] = [$this->formatOptionKeys($option), $this->formatOptionDescription($option)];
        }
      }
      $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
    }

    if (array_key_exists('topics', $data)) {
      $rows = [];
      $output->writeln('');
      $output->writeln('Topics:');
      foreach ($data['topics'] as $topic) {
        $topic_command = \Drush::getApplication()->find($topic);
        $rows[] = [' drush topic ' . $topic, $topic_command->getDescription()];
      }
      $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
    }

    // @todo Fix this variability in key name upstream.
    if (array_key_exists('aliases', $data) ? $data['aliases'] :  array_key_exists('alias', $data) ? [$data['alias']] : []) {
      $output->writeln('');
      $output->writeln('Aliases: ' . implode(', ', $data['aliases']));
    }
  }

  /**
   * @param array $option
   * @return string
   */
  public static function formatOptionKeys($option) {
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
      $option['shortcut']  ? sprintf('-%s, ', $option['shortcut'] ) : ' ',
      sprintf('--%s%s', $option['name'], $value)
    );
    return $synopsis;
  }

  public static function formatOptionDescription($option) {
    $defaults = array_key_exists('defaults', $option) ? ' [default: "' . implode(' ', $option['defaults']) . '"]' : '';
    return $option['description'] . $defaults;
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
