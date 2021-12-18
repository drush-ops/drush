<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Vocabulary;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\core\BundleMachineNameAskTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VocabularyCreateCommands extends DrushCommands implements CustomEventAwareInterface
{
    use BundleMachineNameAskTrait;
    use CustomEventAwareTrait;

    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
    }

    /**
     * Create a new vocabulary
     *
     * @command vocabulary:create
     * @aliases vocabulary-create,vc
     *
     * @option label
     *      The human-readable name of this vocabulary.
     * @option machine-name
     *      A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.
     * @option description
     *      Describe this vocabulary.
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @usage drush vocabulary:create
     *      Create a taxonomy vocabulary by answering the prompts.
     *
     * @validate-module-enabled taxonomy
     *
     * @version 11.0
     * @see \Drupal\taxonomy\VocabularyForm
     */
    public function create(array $options = [
        'label' => InputOption::VALUE_REQUIRED,
        'machine-name' => InputOption::VALUE_REQUIRED,
        'description' => InputOption::VALUE_OPTIONAL,
        'show-machine-names' => InputOption::VALUE_OPTIONAL,
    ]): void
    {
        $this->ensureOption('label', [$this, 'askLabel'], true);
        $this->ensureOption('machine-name', [$this, 'askVocabularyMachineName'], true);
        $this->ensureOption('description', [$this, 'askDescription'], false);

        // Command files may set additional options as desired.
        $handlers = $this->getCustomEventHandlers('vocabulary-set-options');
        foreach ($handlers as $handler) {
            $handler($this->input);
        }

        $bundle = $this->input->getOption('machine-name');
        $definition = $this->entityTypeManager->getDefinition('taxonomy_term');
        $storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');

        $values = [
            $definition->getKey('bundle') => $bundle,
            'status' => true,
            'name' => $this->input()->getOption('label'),
            'description' => $this->input()->getOption('description') ?? '',
            'hierarchy' => 0,
            'weight' => 0,
        ];

        // Command files may customize $values as desired.
        $handlers = $this->getCustomEventHandlers('vocabulary-create');
        foreach ($handlers as $handler) {
            $handler($values);
        }

        $type = $storage->create($values);
        $type->save();

        $this->entityTypeManager->clearCachedDefinitions();
        $this->logResult($type);
    }

    protected function askVocabularyMachineName(): string
    {
        return $this->askMachineName('taxonomy_vocabulary');
    }

    protected function askLabel(): string
    {
        return $this->io()->ask('Human-readable name', null, [static::class, 'validateRequired']);
    }

    protected function askDescription(): ?string
    {
        return $this->io()->ask('Description');
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

    protected function logResult(Vocabulary $type): void
    {
        $this->logger()->success(
            sprintf("Successfully created vocabulary with bundle '%s'", $type->id())
        );

        $this->logger()->success(
            'Further customisation can be done at the following url:'
            . PHP_EOL
            . Url::fromRoute('entity.taxonomy_vocabulary.edit_form', ['taxonomy_vocabulary' => $type->id()])
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
