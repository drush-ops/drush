<?php

namespace Drush\Formatters;

use Consolidation\Filter\FilterOutputData;
use Consolidation\Filter\LogicalOpFactory;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Attributes\FilterDefaultField;
use Drush\Drush;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

trait FormatterTrait
{
    /**
     * Add to the command definition based on data type.
     *  - The --format description is dynamic.
     *  - Add a link in help()
     *
     * @param string $dataType
     *   Usually the same as the return type of a doExecute() method.
     * @param FormatterOptions $formatterOptions
     *   The formatter options for this command.
     */
    public function configureFormatter(string $dataType, FormatterOptions $formatterOptions): void
    {
        $inputOptions = $this->formatterManager->automaticOptions($formatterOptions, $dataType);
        foreach ($inputOptions as $inputOption) {
            $mode = $this->getPrivatePropValue($inputOption, 'mode');
            $suggestedValues = $this->getPrivatePropValue($inputOption, 'suggestedValues');
            $this->addOption($inputOption->getName(), $inputOption->getShortcut(), $mode, $inputOption->getDescription(), $inputOption->getDefault(), $suggestedValues);
        }

        // Append a web link to the command's help.
        // @todo $this->getApplication() throws an Exception - we are called during __construct(). Get base URL from the Container?
        $application = Drush::getApplication();
        if (method_exists($application, 'getDocsBaseUrl')) {
            $url = sprintf('%s/output-formats-filters', $application->getDocsBaseUrl());
            $section = sprintf('Learn more about about output formatting and filtering at %s', $url);
            $help = $this->getHelp();
            $help .= "\n\n" . $section;
            $this->setHelp($help);
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
}
