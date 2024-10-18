<?php

namespace Drush\Formatters;

use Consolidation\Filter\FilterOutputData;
use Consolidation\Filter\LogicalOpFactory;
use Drush\Attributes\FilterDefaultField;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait FormatterTrait
{
    /**
     * Adds options to the command definition based on data type. The --format description is dynamic.
     *
     * @param string $dataType
     *   Usually the same as the return type of a doExecute() method.
     */
    public function addFormatterOptions(string $dataType): void
    {
        $inputOptions = $this->formatterManager->automaticOptions($this->getFormatterOptions(), $dataType);
        foreach ($inputOptions as $inputOption) {
            $mode = $this->getPrivatePropValue($inputOption, 'mode');
            $suggestedValues = $this->getPrivatePropValue($inputOption, 'suggestedValues');
            $this->addOption($inputOption->getName(), $inputOption->getShortcut(), $mode, $inputOption->getDescription(), $inputOption->getDefault(), $suggestedValues);
        }

        // Add the --filter option if the command has a FilterDefaultField attribute.
        $reflection = new \ReflectionObject($this);
        $attributes = $reflection->getAttributes(FilterDefaultField::class);
        if (!empty($attributes)) {
            $instance = $attributes[0]->newInstance();
            $this->addOption('filter', null, InputOption::VALUE_REQUIRED, 'Filter output based on provided expression. Default field: ' . $instance->field);
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

    protected function getPrivatePropValue(mixed $object, $name): mixed
    {
        $rc = new \ReflectionClass($object);
        $prop = $rc->getProperty($name);
        return $prop->getValue($object);
    }

    /**
     * Build the formatter configuration from the command's attributes
     */
    protected function getConfigurationData(): array
    {
        $configurationData = [];
        $reflection = new \ReflectionObject($this);
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof FormatterConfigurationItemProviderInterface) {
                $configurationData = array_merge($configurationData, $instance->getConfigurationItem($attribute));
            }
        }
        return $configurationData;
    }
}
