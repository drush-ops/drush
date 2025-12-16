<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\IoTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: self::NAME,
    description: 'Lists field types.',
)]
#[CLI\DefaultFields(fields: [
    'id',
    'label',
])]
#[CLI\FieldLabels(labels: [
    'id' => 'ID',
    'label' => 'Label',
    'default_widget' => 'Default Widget',
    'default_formatter' => 'Default Formatter',
    'settings' => 'Settings',
])]
#[CLI\FilterDefaultField(field: 'id')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class FieldTypesCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use IoTrait;

    const string NAME = 'field:types';

    public function __construct(
        private readonly FieldTypePluginManagerInterface $typePluginManager,
        private readonly FormatterManager $formatterManager
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setIo($input, $output);

        $processor = static fn (array $definition): array => [
          'id' => $definition['id'],
          'label' => (string) $definition['label'],
          'default_widget' => $definition['default_widget'] ?? null,
          'default_formatter' => $definition['default_formatter'] ?? null,
          'settings' => BaseFieldDefinition::create($definition['id'])->getSettings(),
        ];

        $definitions = \array_map($processor, $this->typePluginManager->getDefinitions());
        $definitions = \array_values($definitions);

        $result = new RowsOfFields($definitions);
        $result->addRendererFunction($this->renderArray(...));
        $this->writeFormattedOutput($input, $output, $result);

        return Command::SUCCESS;
    }

    public function renderArray($key, $value, FormatterOptions $options)
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return implode("\n", $value);
            }

            return Yaml::dump($value);
        }

        return $value;
    }
}
