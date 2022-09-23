<?php

namespace Drush\Commands\help;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Formatters\FormatterInterface;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Drush;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Format an array into CLI help string.
 */
class HelpCLIFormatter implements FormatterInterface
{
    /**
     * @inheritdoc
     */
    public function write(OutputInterface $output, $data, FormatterOptions $options): void
    {
        $formatterManager = new FormatterManager();

        $output->writeln($data['description']);
        if (array_key_exists('help', $data) && $data['help'] != $data['description']) {
            $output->writeln('');
            $output->writeln($data['help']);
        }

        if (array_key_exists('examples', $data)) {
            $output->writeln('');
            $output->writeln('<comment>Examples:</comment>');
            foreach ($data['examples'] as $example) {
                $rows[] = [' ' . $example['usage'], $example['description']];
            }
            $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
        }

        if (array_key_exists('arguments', $data)) {
            $rows = [];
            $output->writeln('');
            $output->writeln('<comment>Arguments:</comment>');
            foreach ($data['arguments'] as $argument) {
                $formatted = $this->formatArgumentName($argument);
                $description = $argument['description'];
                if (isset($argument['defaults'])) {
                    $description .= ' [default: <info>' . implode(',', $argument['defaults']) . '</info>]';
                }
                $rows[] = [' ' . $formatted, $description];
            }
            $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
        }

        $this->cleanOptions($data);
        if (!empty($data['options'])) {
            $rows = [];
            $output->writeln('');
            $output->writeln('<comment>Options:</comment>');
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
            $output->writeln('<comment>Topics:</comment>');
            foreach ($data['topics'] as $topic) {
                $topic_command = Drush::getApplication()->find($topic);
                $rows[] = [' drush topic ' . $topic, $topic_command->getDescription()];
            }
            $formatterManager->write($output, 'table', new RowsOfFields($rows), $options);
        }

        // @todo Fix this variability in key name upstream.
        if (array_key_exists('aliases', $data) ? $data['aliases'] :  (array_key_exists('alias', $data) ? [$data['alias']] : [])) {
            $output->writeln('');
            $output->writeln('<comment>Aliases:</comment> ' . implode(', ', $data['aliases']));
        }
    }

    public static function formatOptionKeys(iterable $option): string
    {
        // Remove leading dashes.
        $option['name'] = substr($option['name'], 2);

        $value = '';
        if ($option['accept_value']) {
            $value = '=' . strtoupper($option['name']);

            if (!$option['is_value_required']) {
                $value = '[' . $value . ']';
            }
        }

        $synopsis = sprintf(
            '%s%s',
            $option['shortcut']  ? sprintf('-%s, ', $option['shortcut']) : ' ',
            sprintf('--%s%s', $option['name'], $value)
        );
        return $synopsis;
    }

    public static function formatOptionDescription($option): string
    {
        $defaults = '';
        if (array_key_exists('defaults', $option)) {
            $defaults = implode(' ', $option['defaults']); //
            // Avoid info tags for large strings https://github.com/drush-ops/drush/issues/4639.
            if (strlen($defaults) <= 100) {
                $defaults = "<info>$defaults</info>";
            }
            $defaults = ' [default: ' . $defaults . ']';
        }
        return $option['description'] . $defaults;
    }

    public static function formatArgumentName($argument)
    {
        $element = $argument['name'];
        if (!$argument['is_required']) {
            $element = '[' . $element . ']';
        } elseif ($argument['is_array']) {
            $element = $element . ' (' . $element . ')';
        }

        if ($argument['is_array']) {
            $element .= '...';
        }

        return $element;
    }

    protected function cleanOptions(&$data): void
    {

        if (array_key_exists('options', $data)) {
            foreach ($data['options'] as $key => $option) {
                // Populate any missing description.
                if (!array_key_exists('description', $option)) {
                    $data['options'][$key]['description'] = '';
                }

                // Remove global options.
                if (self::isGlobalOption($key)) {
                    unset($data['options'][$key]);
                }
            }
        }
    }

    public static function isGlobalOption($name): bool
    {
        $application = Drush::getApplication();
        $def = $application->getDefinition();
        return array_key_exists($name, $def->getOptions()) || substr($name, 0, 6) == 'notify' || substr($name, 0, 3) == 'xh-' || substr($name, 0, 9) == 'druplicon';
    }
}
