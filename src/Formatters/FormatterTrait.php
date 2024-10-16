<?php

namespace Drush\Formatters;

use Consolidation\Filter\FilterOutputData;
use Consolidation\Filter\LogicalOpFactory;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Attributes\FilterDefaultField;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait FormatterTrait
{
    public function addFormatterOptions()
    {
        $formatterOptions = new FormatterOptions($this->getConfigurationData(), []);
        $reflection = new \ReflectionMethod($this, 'doExecute');
        $returnType = $reflection->getReturnType();
        if ($returnType instanceof \ReflectionNamedType) {
            $dataType = $returnType->getName();
        } else {
            throw new \Exception($reflection->getDeclaringClass() . '::doExecute method must specify a return type.');
        }
        $inputOptions = $this->formatterManager->automaticOptions($formatterOptions, $dataType);
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

    /**
     * Format the structured data as per user input and the command definition.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $configurationData = $this->getConfigurationData();
        $formatterOptions = new FormatterOptions($configurationData, $input->getOptions());
        $formatterOptions->setInput($input);
        $data = $this->doExecute($input, $output);
        if ($input->hasOption('filter')) {
            $data = $this->alterResult($data, $input);
        }
        $this->formatterManager->write($output, $input->getOption('format'), $data, $formatterOptions);
        return static::SUCCESS;
    }

    protected function alterResult($result, InputInterface $input): mixed
    {
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

    /**
     * Override this method with the actual command logic. Type hint the return value
     * to help the formatter know what to expect.
     */
    abstract protected function doExecute(InputInterface $input, OutputInterface $output);
}
