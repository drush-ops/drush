<?php

namespace Drush\Formatters;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


trait FormatterTrait
{
    public function addFormatterOptions()
    {
        $formatterOptions = new FormatterOptions($this->getConfigurationData(), []);
        $reflection = new \ReflectionMethod($this, 'doExecute');
        $inputOptions = $this->formatterManager->automaticOptions($formatterOptions, $reflection->getReturnType()->getName());
        foreach ($inputOptions as $inputOption) {
            $mode = $this->getPrivatePropValue($inputOption, 'mode');
            $suggestedValues = $this->getPrivatePropValue($inputOption, 'suggestedValues');
            $this->addOption($inputOption->getName(), $inputOption->getShortcut(), $mode, $inputOption->getDescription(), $inputOption->getDefault(), $suggestedValues);
        }
    }

    /**
     * Format the structured data as per user input and the command definition.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $configurationData = $this->getConfigurationData($this);
        $formatterOptions = new FormatterOptions($configurationData, $input->getOptions());
        $formatterOptions->setInput($input);
        $data = $this->doExecute($input, $output);
        $this->formatterManager->write($output, $input->getOption('format'), $data, $formatterOptions);
        return Command::SUCCESS;
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
