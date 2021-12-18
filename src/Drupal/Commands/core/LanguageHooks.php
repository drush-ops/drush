<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\Entity\ContentLanguageSettings;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class LanguageHooks extends DrushCommands
{
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;
    /** @var LanguageManagerInterface */
    protected $languageManager;

    public function __construct(
        ModuleHandlerInterface $moduleHandler,
        LanguageManagerInterface $languageManager
    ) {
        $this->moduleHandler = $moduleHandler;
        $this->languageManager = $languageManager;
    }

    /** @hook option nodetype:create */
    public function hookNodeTypeOptions(Command $command, AnnotationData $annotationData): void
    {
        $this->doAddOptions($command);
    }

    /** @hook option vocabulary:create */
    public function hookVocabularyOptions(Command $command, AnnotationData $annotationData): void
    {
        $this->doAddOptions($command);
    }

    /** @hook on-event node-type-set-options */
    public function hookNodeTypeSetOptions(InputInterface $input): void
    {
        $this->doSetOptions();
    }

    /** @hook on-event vocabulary-set-options */
    public function hookVocabularySetOptions(InputInterface $input): void
    {
        $this->doSetOptions();
    }

    /** @hook on-event nodetype-create */
    public function hookNodeTypeCreate(array &$values): void
    {
        $this->doSetValues($values);
    }

    /** @hook on-event vocabulary-create */
    public function hookVocabularyCreate(array &$values): void
    {
        $this->doSetValues($values);
    }

    /** @hook post-command nodetype:create */
    public function hookPostNodeTypeCreate($result, CommandData $commandData): void
    {
        $this->doCreateContentLanguageSettings('node');
    }

    /** @hook post-command vocabulary:create */
    public function hookPostVocabularyCreate($result, CommandData $commandData): void
    {
        $this->doCreateContentLanguageSettings('taxonomy_vocabulary');
    }

    protected function doAddOptions(Command $command): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'default-language',
            '',
            InputOption::VALUE_OPTIONAL,
            'The default language of new entities.'
        );

        $command->addOption(
            'show-language-selector',
            '',
            InputOption::VALUE_OPTIONAL,
            'Whether to show the language selector on create and edit pages.'
        );
    }

    protected function doSetOptions(): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $this->ensureOption('default-language', [$this, 'askLanguageDefault'], true);
        $this->ensureOption('show-language-selector', [$this, 'askLanguageShowSelector'], true);
    }

    protected function doSetValues(array &$values): array
    {
        if (!$this->isInstalled()) {
            return $values;
        }

        $values['langcode'] = $this->input->getOption('default-language');
        $values['dependencies']['module'][] = 'language';

        return $values;
    }

    protected function doCreateContentLanguageSettings(string $entityTypeId): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $bundle = $this->input->getOption('machine-name');
        $defaultLanguage = $this->input->getOption('default-language');
        $showLanguageSelector = (bool) $this->input->getOption('show-language-selector');

        $config = ContentLanguageSettings::loadByEntityTypeBundle($entityTypeId, $bundle);
        $config->setDefaultLangcode($defaultLanguage)
            ->setLanguageAlterable($showLanguageSelector)
            ->save();
    }

    protected function askLanguageDefault(): string
    {
        $options = [
            LanguageInterface::LANGCODE_SITE_DEFAULT => dt("Site's default language (@language)", ['@language' => \Drupal::languageManager()->getDefaultLanguage()->getName()]),
            'current_interface' => dt('Interface text language selected for page'),
            'authors_default' => dt("Author's preferred language"),
        ];

        $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);

        foreach ($languages as $langcode => $language) {
            $options[$langcode] = $language->isLocked()
                ? dt('- @name -', ['@name' => $language->getName()])
                : $language->getName();
        }

        return $this->io()->choice('Default language', $options, 1);
    }

    protected function askLanguageShowSelector(): bool
    {
        return $this->io()->confirm('Show language selector on create and edit pages', false);
    }

    protected function isInstalled(): bool
    {
        return $this->moduleHandler->moduleExists('language');
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
}
