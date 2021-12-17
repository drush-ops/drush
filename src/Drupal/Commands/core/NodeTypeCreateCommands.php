<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class NodeTypeCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use BundleMachineNameAskTrait;
    use CustomEventAwareTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * Create a new node type
     *
     * @command nodetype:create
     * @aliases nodetype-create,ntc
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @option label
     *      The human-readable name of this content type.
     * @option machine-name
     *      A unique machine-readable name for this content type. It must only contain
     *      lowercase letters, numbers, and underscores.
     * @option description
     *      This text will be displayed on the Add new content page.
     *
     * @option title-label
     *      The label of the title field
     * @option preview-before-submit
     *      Preview before submitting (disabled, optional or required)
     * @option submission-guidelines
     *      Explanation or submission guidelines. This text will be displayed at the top
     *      of the page when creating or editing content of this type.
     *
     * @option status
     *      The default value of the Published field
     * @option promote
     *      The default value of the Promoted to front page field
     * @option sticky
     *      The default value of the Sticky at top of lists field
     * @option create-revision
     *      The default value of the Create new revision field
     *
     * @option display-submitted
     *      Display author username and publish date
     *
     * @usage drush nodetype:create
     *      Create a node type by answering the prompts.
     *
     * @validate-module-enabled node
     *
     * @version 11.0
     * @see \Drupal\node\NodeTypeForm
     */
    public function create(array $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'title-label' => InputOption::VALUE_OPTIONAL,
        'preview-before-submit' => InputOption::VALUE_OPTIONAL,
        'submission-guidelines' => InputOption::VALUE_OPTIONAL,
        'status' => InputOption::VALUE_OPTIONAL,
        'promote' => InputOption::VALUE_OPTIONAL,
        'sticky' => InputOption::VALUE_OPTIONAL,
        'create-revision' => InputOption::VALUE_OPTIONAL,
        'display-submitted' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $this->ensureOption('label', [$this, 'askLabel'], true);
        $this->ensureOption('machine-name', [$this, 'askNodeTypeMachineName'], true);
        $this->ensureOption('description', [$this, 'askDescription'], false);

        // Submission form settings
        $this->ensureOption('title-label', [$this, 'askSubmissionTitleLabel'], true);
        $this->ensureOption('preview-before-submit', [$this, 'askSubmissionPreviewMode'], true);
        $this->ensureOption('submission-guidelines', [$this, 'askSubmissionHelp'], false);

        // Publishing options
        $this->ensureOption('status', [$this, 'askPublished'], true);
        $this->ensureOption('promote', [$this, 'askPromoted'], true);
        $this->ensureOption('sticky', [$this, 'askSticky'], true);
        $this->ensureOption('create-revision', [$this, 'askCreateRevision'], true);

        // Display settings
        $this->ensureOption('display-submitted', [$this, 'askDisplaySubmitted'], true);

        // Command files may set additional options as desired.
        $handlers = $this->getCustomEventHandlers('node-type-set-options');
        foreach ($handlers as $handler) {
            $handler($this->input);
        }

        $bundle = $this->input()->getOption('machine-name');
        $definition = $this->entityTypeManager->getDefinition('node');
        $storage = $this->entityTypeManager->getStorage('node_type');

        $values = [
            $definition->getKey('status') => true,
            $definition->getKey('bundle') => $bundle,
            'name' => $this->input()->getOption('label'),
            'description' => $this->input()->getOption('description') ?? '',
            'new_revision' => $this->input()->getOption('create-revision'),
            'help' => $this->input()->getOption('submission-guidelines') ?? '',
            'preview_mode' => $this->input()->getOption('preview-before-submit'),
            'display_submitted' => $this->input()->getOption('display-submitted'),
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('nodetype-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $type = $storage->create($values);
        $type->save();

        // Update title field definition.
        $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
        $titleField = $fields['title'];
        $titleLabel = $this->input()->getOption('title-label');

        if ($titleLabel && $titleLabel !== $titleField->getLabel()) {
            $titleField->getConfig($bundle)
                ->setLabel($titleLabel)
                ->save();
        }

        // Update workflow options
        foreach (['status', 'promote', 'sticky'] as $fieldName) {
            $node = $this->entityTypeManager->getStorage('node')->create(['type' => $bundle]);
            $value = (bool) $this->input()->getOption($fieldName);

            if ($node->get($fieldName)->value != $value) {
                $fields[$fieldName]
                    ->getConfig($bundle)
                    ->setDefaultValue($value)
                    ->save();
            }
        }

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($type);
    }

    protected function askNodeTypeMachineName(): string
    {
        return $this->askMachineName('node');
    }

    protected function askLabel(): string
    {
        return $this->io()->ask('Human-readable name', null, [static::class, 'validateRequired']);
    }

    protected function askDescription(): ?string
    {
        return $this->io()->ask('Description');
    }

    protected function askSubmissionTitleLabel(): string
    {
        return $this->io()->ask('Title field label', 'Title');
    }

    protected function askSubmissionPreviewMode(): int
    {
        $options = [
            DRUPAL_DISABLED => dt('Disabled'),
            DRUPAL_OPTIONAL => dt('Optional'),
            DRUPAL_REQUIRED => dt('Required'),
        ];

        return $this->io()->choice('Preview before submitting', $options, DRUPAL_OPTIONAL);
    }

    protected function askSubmissionHelp(): ?string
    {
        return $this->io()->ask('Explanation or submission guidelines');
    }

    protected function askPublished(): bool
    {
        return $this->io()->confirm('Published', true);
    }

    protected function askPromoted(): bool
    {
        return $this->io()->confirm('Promoted to front page', true);
    }

    protected function askSticky(): bool
    {
        return $this->io()->confirm('Sticky at top of lists', false);
    }

    protected function askCreateRevision(): bool
    {
        return $this->io()->confirm('Create new revision', true);
    }

    protected function askDisplaySubmitted(): bool
    {
        return $this->io()->confirm('Display author and date information', true);
    }

    protected function ensureOption(string $name, callable $asker, bool $required): void
    {
        $value = $this->input->getOption($name);

        if ($value === null) {
            $value = $asker();
        }

        if ($required && $value === null) {
            throw new \InvalidArgumentException(dt('The %optionName option is required.', [
                '%optionName' => $name,
            ]));
        }

        $this->input->setOption($name, $value);
    }

    protected function logResult(NodeType $type): void
    {
        $this->logger()->success(
            sprintf('Successfully created node type with bundle \'%s\'', $type->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . Url::fromRoute('entity.node_type.edit_form', ['node_type' => $type->id()])
                ->setAbsolute(true)
                ->toString()
        );
    }

    public static function validateRequired(?string $value): string
    {
        // FALSE is not considered as empty value because question helper use
        // it as negative answer on confirmation questions.
        if ($value === null || $value === '') {
            throw new \UnexpectedValueException('This value is required.');
        }

        return $value;
    }
}
