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
    public function hookOption(Command $command, AnnotationData $annotationData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $command->addOption(
            'default-language',
            '',
            InputOption::VALUE_OPTIONAL,
            'The default language of new nodes.'
        );

        $command->addOption(
            'show-language-selector',
            '',
            InputOption::VALUE_OPTIONAL,
            'Whether to show the language selector on create and edit pages.'
        );
    }

    /** @hook on-event node-type-set-options */
    public function hookSetOptions(InputInterface $input): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $this->ensureOption('default-language', [$this, 'askLanguageDefault'], true);
        $this->ensureOption('show-language-selector', [$this, 'askLanguageShowSelector'], true);
    }

    /** @hook on-event nodetype-create */
    public function hookCreate(array &$values): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $values['langcode'] = $this->input->getOption('default-language');
        $values['dependencies']['module'][] = 'language';
    }

    /** @hook post-command nodetype:create */
    public function hookPostFieldCreate($result, CommandData $commandData): void
    {
        if (!$this->isInstalled()) {
            return;
        }

        $bundle = $this->input->getOption('machine-name');
        $defaultLanguage = $this->input->getOption('default-language');
        $showLanguageSelector = (bool) $this->input->getOption('show-language-selector');

        $config = ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);
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
