<?php

namespace Drush\Drupal\Commands\field;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use function dt;
use function t;

class FieldBaseOverrideCreateCommands extends DrushCommands
{
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityTypeBundleInfoInterface */
    protected $entityTypeBundleInfo;
    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityTypeBundleInfoInterface $entityTypeBundleInfo,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * Create a new base field override
     *
     * @command field:base-override-create
     * @aliases bfoc
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param string $bundle
     *      The machine name of the bundle
     *
     * @option field-name
     *      A unique machine-readable name containing letters, numbers, and underscores.
     * @option field-label
     *      The field label
     * @option field-description
     *      The field description
     * @option is-required
     *      Whether the field is required
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush field:base-field-override-create
     *      Create a base field override by answering the prompts.
     * @usage drush field:base-field-override-create taxonomy_term tag
     *      Create a base field override and fill in the remaining information through prompts.
     * @usage drush field:base-field-override-create taxonomy_term tag --field-name=name --field-label=Label --is-required=1
     *      Create a base field override in a completely non-interactive way.
     *
     * @see \Drupal\field_ui\Form\FieldConfigEditForm
     * @see \Drupal\field_ui\Form\FieldStorageConfigEditForm
     *
     * @version 11.0
     */
    public function create(?string $entityType = null, ?string $bundle = null, array $options = [
        'field-name' => InputOption::VALUE_REQUIRED,
        'field-label' => InputOption::VALUE_REQUIRED,
        'field-description' => InputOption::VALUE_REQUIRED,
        'is-required' => InputOption::VALUE_REQUIRED,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $this->input->setArgument('entityType', $entityType = $entityType ?? $this->askEntityType());
        $this->validateEntityType($entityType);

        $this->input->setArgument('bundle', $bundle = $bundle ?? $this->askBundle());
        $this->validateBundle($entityType, $bundle);

        $fieldName = $this->input->getOption('field-name') ?? $this->askFieldName($entityType);
        $this->input->setOption('field-name', $fieldName);

        if ($fieldName === '') {
            throw new \InvalidArgumentException(dt('The %optionName option is required.', [
                '%optionName' => 'field-name',
            ]));
        }

        /** @var BaseFieldOverride|BaseFieldDefinition $definition */
        $definition = BaseFieldOverride::loadByName($entityType, $bundle, $fieldName)
            ?? $this->getBaseFieldDefinition($entityType, $fieldName);

        if ($definition === null) {
            throw new \InvalidArgumentException(
                t("Base field with name ':fieldName' does not exist on bundle ':bundle'.", [
                    ':fieldName' => $fieldName,
                    ':bundle' => $bundle,
                ])
            );
        }

        $this->input->setOption(
            'field-label',
            $this->input->getOption('field-label') ?? $this->askFieldLabel((string) $definition->getLabel())
        );
        $this->input->setOption(
            'field-description',
            $this->input->getOption('field-description') ?? $this->askFieldDescription($definition->getDescription())
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

        return $this->io()->choice('Field name', $choices);
    }

    protected function askFieldLabel(string $default): string
    {
        return $this->io()->ask('Field label', $default);
    }

    protected function askFieldDescription(?string $default): ?string
    {
        return $this->io()->ask('Field description', $default);
    }

    protected function askRequired(bool $default): bool
    {
        return $this->io()->askQuestion(new ConfirmationQuestion('Required', $default));
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

        return $override;
    }

    protected function logResult(BaseFieldOverride $baseFieldOverride): void
    {
        $this->logger()->success(
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
