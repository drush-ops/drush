<?php

namespace Drush\Formatters;

use Consolidation\Filter\FilterOutputData;
use Consolidation\Filter\LogicalOpFactory;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Attributes\FilterDefaultField;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait FormatterTrait
{
    protected FormatterOptions $formatterOptions;

    /**
     * Filter, format, and write to the output
     */
    protected function writeFormattedOutput(InputInterface $input, OutputInterface $output, $data): void
    {
        if (!isset($this->formatterManager)) {
            throw new \Exception('\Consolidation\OutputFormatters\FormatterManager must be injected into the command during __construct().');
        }

        if (is_object($data) || is_array($data) || is_string($data)) {
            $data = $this->alterResult($data, $input);
            $format = $input->getOption('format');
            if ($input->hasOption('field') && $input->getOption('field')) {
                $format = 'string';
            }
            $this->formatterManager->write($output, $format, $data, $this->getFormatterOptions()->setInput($input)->setOptions($input->getOptions()));
        }
    }

    protected function alterResult($result, InputInterface $input): mixed
    {
        if (!$input->hasOption('filter') || !$input->getOption('filter')) {
            return $result;
        }
        $expression = $input->getOption('filter');
        $reflection = new \ReflectionObject($this);
        $attributes = $reflection->getAttributes(FilterDefaultField::class);
        $instance = $attributes[0]->newInstance();
        $factory = LogicalOpFactory::get();
        $op = $factory->evaluate($expression, $instance->field);
        $filter = new FilterOutputData();
        return $this->wrapFilteredResult($filter->filter($result, $op), $result);
    }

    /**
     * If the source data was wrapped in a marker class such
     * as RowsOfFields, then re-apply the wrapper.
     */
    protected function wrapFilteredResult($data, $source)
    {
        if (!$source instanceof \ArrayObject) {
            return $data;
        }
        $sourceClass = get_class($source);

        return new $sourceClass($data);
    }

    protected function getFormatterOptions(): FormatterOptions
    {
        return $this->formatterOptions;
    }

    /**
     * Public because is used by FormatterListener.
     */
    public function setFormatterOptions(FormatterOptions $formatterOptions): void
    {
        $this->formatterOptions = $formatterOptions;
    }

    protected function getPrivatePropValue(mixed $object, $name): mixed
    {
        $rc = new \ReflectionClass($object);
        $prop = $rc->getProperty($name);
        return $prop->getValue($object);
    }
}
