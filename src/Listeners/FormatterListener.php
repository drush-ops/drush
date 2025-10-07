<?php

declare(strict_types=1);

namespace Drush\Listeners;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Event\ConsoleDefinitionsEvent;
use Drush\Formatters\FormatterConfigurationItemProviderInterface;
use ReflectionObject;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class FormatterListener
{
    use AutowireTrait;

    public function __construct(
        protected FormatterManager $formatterManager,
    ) {
    }

    public function __invoke(ConsoleDefinitionsEvent $event): void
    {
        foreach ($event->getApplication()->all() as $id => $command) {
            // Support invokable commands (Symfony Console 7.4+).
            $code = method_exists($command, 'getCode') && $command->getCode() ? $command->getCode() : $command;
            $reflectionObject = new \ReflectionObject($code);
            if (!$attributes = $reflectionObject->getAttributes(CLI\Formatter::class)) {
                continue;
            }

            /** @var \Drush\Attributes\Formatter $attribute */
            $attribute = $attributes[0]->newInstance();
            $configurationData = $this->getConfigurationData($reflectionObject);
            $formatterOptions = new FormatterOptions($configurationData, []);
            assert(method_exists($command, 'setFormatterOptions'));
            $command->setFormatterOptions($formatterOptions);

            $inputOptions = $this->formatterManager->automaticOptions($formatterOptions, $attribute->returnType);
            foreach ($inputOptions as $inputOption) {
                if ($command->getDefinition()->hasOption($inputOption->getName())) {
                    // This Listener also fires during full bootstrap, so skip if already present.
                    continue;
                }
                $mode = $this->getPrivatePropValue($inputOption, 'mode');
                $suggestedValues = $this->getPrivatePropValue($inputOption, 'suggestedValues');
                $command->addOption($inputOption->getName(), $inputOption->getShortcut(), $mode, $inputOption->getDescription(), $inputOption->getDefault(), $suggestedValues);
            }
            // The command must have a --format option, even if the above didn't add it.
            if (!$command->getDefinition()->hasOption('format')) {
                $command->addOption(name:'format', mode: InputOption::VALUE_REQUIRED, description: 'A format for printing the returned data');
            }
            // Use the command's fallback for --format. The automatic option above doesn't always get it right.
            $command->getDefinition()->getOption('format')->setDefault($attribute->defaultFormatter);
        }
    }

    /**
     * Build the formatter configuration from the command's attributes
     */
    protected function getConfigurationData(ReflectionObject $reflection): array
    {
        $configurationData = [];
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof FormatterConfigurationItemProviderInterface) {
                $configurationData = array_merge($configurationData, $instance->getConfigurationItem($attribute));
            }
        }
        return $configurationData;
    }

    protected function getPrivatePropValue(mixed $object, $name): mixed
    {
        $rc = new \ReflectionClass($object);
        $prop = $rc->getProperty($name);
        return $prop->getValue($object);
    }
}
