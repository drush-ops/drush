<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\IoTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function dt;

/**
 * @see \Drupal\field_ui\Form\FieldConfigEditForm
 * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
 */
#[AsCommand(
    name: self::BASE_OVERRIDE_CREATE,
    description: 'Create a new base field override.',
    aliases: ['bfoc'],
)]
#[CLI\Version(version: '11.0')]
final class FieldBaseOverrideCreateCommands extends Command
{
    use AutowireTrait;
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use IoTrait;

    const string BASE_OVERRIDE_CREATE = 'field:base-override-create';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        private readonly EntityFieldManagerInterface $entityFieldManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'entityType', mode: InputArgument::OPTIONAL, description: 'The machine name of the entity type')
            ->addArgument(name: 'bundle', mode: InputArgument::OPTIONAL, description: 'The machine name of the bundle')
            ->addOption(name: 'field-name', mode: InputOption::VALUE_REQUIRED, description: 'A unique machine-readable name containing letters, numbers, and underscores')
            ->addOption(name: 'field-label', mode: InputOption::VALUE_REQUIRED, description: 'The field label')
            ->addOption(name: 'field-description', mode: InputOption::VALUE_REQUIRED, description: 'The field description')
            ->addOption(name: 'is-required', mode: InputOption::VALUE_REQUIRED, description: 'Whether the field is required')
            ->addOption(name: 'show-machine-names', mode: InputOption::VALUE_OPTIONAL, description: 'Show machine names instead of labels in option lists')
            ->addUsage('field:base-override-create taxonomy_term tag')
            ->addUsage('field:base-override-create taxonomy_term tag --field-name=name --field-label=Label --is-required=1');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setIo($input, $output);

        $entityType = $input->getArgument('entityType') ?? $this->askEntityType();
        $this->input->setArgument('entityType', $entityType);
        $this->validateEntityType($entityType);

        $bundle = $input->getArgument('bundle') ?? $this->askBundle();
        $this->input->setArgument('bundle', $bundle);
        $this->validateBundle($entityType, $bundle);

        $fieldName = $this->input->getOption('field-name') ?? $this->askFieldName($entityType);
        $this->input->setOption('field-name', $fieldName);

        if ($fieldName === null) {
            throw new \InvalidArgumentException(dt('The !optionName option is required.', [
                '!optionName' => 'field-name',
            ]));
        }

        /** @var BaseFieldOverride|BaseFieldDefinition|null $definition */
        $definition = BaseFieldOverride::loadByName($entityType, $bundle, $fieldName)
            ?? $this->getBaseFieldDefinition($entityType, $fieldName);

        if ($definition === null) {
            throw new \InvalidArgumentException(
                dt("Base field with name '!fieldName' does not exist on bundle '!bundle'.", [
                    '!fieldName' => $fieldName,
                    '!bundle' => $bundle,
                ])
            );
        }

        $this->input->setOption(
            'field-label',
            $this->input->getOption('field-label') ?? $this->askFieldLabel((string) $definition->getLabel())
        );
        $this->input->setOption(
            'field-description',
            $this->input->getOption('field-description') ?? $this->askFieldDescription((string) $definition->getDescription())
        );
        $this->input->setOption(
            'is-required',
            (bool) ($this->input->getOption('is-required') ?? $this->askRequired($definition->isRequired()))
        );

        $fieldName = $this->input->getOption('field-name');
        $fieldLabel = $this->input->getOption('field-label');
        $fieldDescription = $this->input->getOption('field-description');
        $isRequired = $this->input->getOption('is-required');

        $baseFieldOverride = $this->createBaseFieldOverride($entityType, $bundle, $fieldName, $fieldLabel, $fieldDescription, $isRequired);

        $this->logResult($baseFieldOverride);

        return self::SUCCESS;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionType() === CompletionInput::TYPE_ARGUMENT_VALUE) {
            if ($input->getCompletionName() === 'entityType') {
                $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
            }

            if ($input->getCompletionName() === 'bundle') {
                $entityTypeId = $input->getArgument('entityType');
                $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

                $suggestions->suggestValues(array_keys($bundleInfo));
            }
        }

        if ($input->getCompletionType() === CompletionInput::TYPE_OPTION_VALUE) {
            if ($input->getCompletionName() === 'field-name') {
                $entityTypeId = $input->getArgument('entityType');
                $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityTypeId);
                $suggestions->suggestValues(array_keys($definitions));
            }
        }
    }

    protected function askFieldName(string $entityType): ?string
    {
        /** @var BaseFieldDefinition[] $definitions */
        $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);
        $choices = [];

        foreach ($definitions as $definition) {
            $label = $this->input->getOption('show-machine-names') ? $definition->getName() : (string) $definition->getLabel();
            $choices[$definition->getName()] = $label;
        }

        return $this->io()->select('Field name', $choices) ?: null;
    }

    protected function askFieldLabel(string $default): string
    {
        return $this->io()->ask('Field label', default: $default);
    }

    protected function askFieldDescription(?string $default): ?string
    {
        return $this->io()->ask('Field description', default: $default);
    }

    protected function askRequired(bool $default): bool
    {
        return $this->io()->confirm('Required', default: $default);
    }

    protected function createBaseFieldOverride(string $entityType, string $bundle, string $fieldName, $fieldLabel, $fieldDescription, bool $isRequired): BaseFieldOverride
    {
        $definition = $this->getBaseFieldDefinition($entityType, $fieldName);
        $override = BaseFieldOverride::loadByName($entityType, $bundle, $fieldName)
            ?? BaseFieldOverride::createFromBaseFieldDefinition($definition, $bundle);

        $override
            ->setLabel($fieldLabel)
            ->setDescription($fieldDescription)
            ->setRequired($isRequired)
            ->save();

        assert($override instanceof BaseFieldOverride);
        return $override;
    }

    protected function logResult(BaseFieldOverride $baseFieldOverride): void
    {
        $this->io()->success(
            sprintf(
                'Successfully created base field override \'%s\' on %s with bundle \'%s\'',
                $baseFieldOverride->getName(),
                $baseFieldOverride->getTargetEntityTypeId(),
                $baseFieldOverride->getTargetBundle()
            )
        );
    }

    protected function getBaseFieldDefinition(string $entityType, string $fieldName): ?BaseFieldDefinition
    {
        /** @var BaseFieldDefinition[] $definitions */
        $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        return $definitions[$fieldName] ?? null;
    }
}
