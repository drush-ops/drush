<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Add and import languages.',
    aliases: ['language-add'],
    hidden: true,
)]
#[CLI\ValidateModulesEnabled(modules: ['language'])]
final class LanguageAddCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'language:add';

    public function __construct(
        protected readonly LanguageManagerInterface $languageManager,
        protected readonly ModuleHandlerInterface $moduleHandler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('langcode', InputArgument::REQUIRED, 'A comma delimited list of language codes.')
            ->addOption('skip-translations', null, InputOption::VALUE_NONE, 'Prevent translations from being downloaded and/or imported.')
            ->addUsage('language:add nl,fr')
            ->addUsage('language:add nl --skip-translations');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $langcode = $input->getArgument('langcode');
        $skipTranslations = $input->getOption('skip-translations');

        if ($langcodes = StringUtils::csvToArray($langcode)) {
            $langcodes = array_unique($langcodes);
            $langcodes = $this->filterValidLangcode($langcodes);
            $langcodes = $this->filterNewLangcode($langcodes, $io);
            if ($langcodes === []) {
                return self::SUCCESS;
            }

            foreach ($langcodes as $langcode) {
                $language = ConfigurableLanguage::createFromLangcode($langcode);
                $language->save();

                $io->success(dt('Added language @language', [
                    '@language' => $language->label(),
                ]));
            }

            if ($skipTranslations) {
                return self::SUCCESS;
            }

            if ($this->moduleHandler->moduleExists('locale')) {
                $this->setBatchLanguageImport($langcodes);
                drush_backend_batch_process();
            }
        }

        return self::SUCCESS;
    }

    /**
     * Filters valid language codes.
     *
     * @throws \Exception
     *   Exception when a language code is not in the standard language list.
     */
    private function filterValidLangcode(array $langcodes): array
    {
        $standardLanguages = $this->languageManager->getStandardLanguageList();
        foreach ($langcodes as $key => $langcode) {
            if (!isset($standardLanguages[$langcode])) {
                throw new \Exception(dt('Unknown language: !langcode', [
                    '!langcode' => $langcode
                ]));
            }
        }

        return $langcodes;
    }

    /**
     * Filters new language codes.
     */
    private function filterNewLangcode(array $langcodes, DrushStyle $io): array
    {
        $enabledLanguages = $this->languageManager->getLanguages();
        foreach ($langcodes as $key => $langcode) {
            if (isset($enabledLanguages[$langcode])) {
                $io->warning(dt('The language !langcode is already enabled.', [
                    '!langcode' => $langcode
                ]));
                unset($langcodes[$key]);
            }
        }

        return $langcodes;
    }

    /**
     * Sets a batch to download and import translations and update configurations.
     *
     * @param $langcodes
     */
    private function setBatchLanguageImport(array $langcodes): void
    {
        $this->moduleHandler->loadInclude('locale', 'inc', 'locale.translation');
        $this->moduleHandler->loadInclude('locale', 'inc', 'locale.fetch');
        $this->moduleHandler->loadInclude('locale', 'inc', 'locale.bulk');
        $translationOptions = _locale_translation_default_update_options();

        locale_translation_clear_status();
        $batch = locale_translation_batch_update_build([], $langcodes, $translationOptions);
        batch_set($batch);
        if ($batch = locale_config_batch_update_components($translationOptions, $langcodes)) {
            batch_set($batch);
        }
    }
}
