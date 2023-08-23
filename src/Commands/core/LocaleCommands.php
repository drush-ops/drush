<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Component\Gettext\PoStreamWriter;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\PoDatabaseReader;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\CommandFailedException;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class LocaleCommands extends DrushCommands
{
    const CHECK = 'locale:check';
    const CLEAR = 'locale:clear-status';
    const UPDATE = 'locale:update';
    const EXPORT = 'locale:export';
    const IMPORT = 'locale:import';
    const IMPORT_ALL = 'locale:import-all';

    protected function getLanguageManager(): LanguageManagerInterface
    {
        return $this->languageManager;
    }

    protected function getConfigFactory(): ConfigFactoryInterface
    {
        return $this->configFactory;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
    }

    public function getState(): StateInterface
    {
        return $this->state;
    }

    public function __construct(protected LanguageManagerInterface $languageManager, protected ConfigFactoryInterface $configFactory, protected ModuleHandlerInterface $moduleHandler, protected StateInterface $state)
    {
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('language_manager'),
            $container->get('config.factory'),
            $container->get('module_handler'),
            $container->get('state')
        );

        return $commandHandler;
    }

    /**
     * Checks for available translation updates.
     */
    #[CLI\Command(name: self::CHECK, aliases: ['locale-check'])]
    #[CLI\ValidateModulesEnabled(modules: ['locale'])]
    public function check(): void
    {
        $this->getModuleHandler()->loadInclude('locale', 'inc', 'locale.compare');

        // Check translation status of all translatable project in all languages.
        // First we clear the cached list of projects. Although not strictly
        // necessary, this is helpful in case the project list is out of sync.
        locale_translation_flush_projects();
        locale_translation_check_projects();

        // Execute a batch if required. A batch is only used when remote files
        // are checked.
        if (batch_get()) {
            drush_backend_batch_process();
        }
    }

    /**
     * Clears the translation status.
     */
    #[CLI\Command(name: self::CLEAR, aliases: ['locale-clear-status'])]
    #[CLI\ValidateModulesEnabled(modules: ['locale'])]
    #[CLI\Version(version: '11.5')]
    public function clearStatus(): void
    {
        locale_translation_clear_status();
    }

    /**
     * Imports the available translation updates.
     *
     * @see TranslationStatusForm::buildForm()
     * @see TranslationStatusForm::prepareUpdateData()
     * @see TranslationStatusForm::submitForm()
     *
     * @todo This can be simplified once https://www.drupal.org/node/2631584 lands
     *   in Drupal core.
     */
    #[CLI\Command(name: self::UPDATE, aliases: ['locale-update'])]
    #[CLI\Option(name: 'langcodes', description: 'A comma-separated list of language codes to update. If omitted, all translations will be updated.')]
    #[CLI\ValidateModulesEnabled(modules: ['locale'])]
    public function update($options = ['langcodes' => self::REQ]): void
    {
        $module_handler = $this->getModuleHandler();
        $module_handler->loadInclude('locale', 'fetch.inc');
        $module_handler->loadInclude('locale', 'bulk.inc');

        $langcodes = [];
        foreach (locale_translation_get_status() as $project_id => $project) {
            foreach ($project as $langcode => $project_info) {
                if (!empty($project_info->type) && !in_array($langcode, $langcodes)) {
                    $langcodes[] = $langcode;
                }
            }
        }

        if ($passed_langcodes = $options['langcodes']) {
            $langcodes = array_intersect($langcodes, explode(',', $passed_langcodes));
        }

        // Deduplicate the list of langcodes since each project may have added the
        // same language several times.
        $langcodes = array_unique($langcodes);

        $projects = [];

        // Set the translation import options. This determines if existing
        // translations will be overwritten by imported strings.
        $translationOptions = _locale_translation_default_update_options();

        // If the status was updated recently we can immediately start fetching the
        // translation updates. If the status is expired we clear it an run a batch to
        // update the status and then fetch the translation updates.
        $last_checked = $this->getState()->get('locale.translation_last_checked');
        if ($last_checked < time() - LOCALE_TRANSLATION_STATUS_TTL) {
            locale_translation_clear_status();
            $batch = locale_translation_batch_update_build([], $langcodes, $translationOptions);
            batch_set($batch);
        } else {
            // Set a batch to download and import translations.
            $batch = locale_translation_batch_fetch_build($projects, $langcodes, $translationOptions);
            batch_set($batch);
            // Set a batch to update configuration as well.
            if ($batch = locale_config_batch_update_components($translationOptions, $langcodes)) {
                batch_set($batch);
            }
        }

        drush_backend_batch_process();
    }

    /**
     * Exports to a gettext translation file.
     *
     * See Drupal Core: \Drupal\locale\Form\ExportForm::submitForm
     */
    #[CLI\Command(name: self::EXPORT, aliases: ['locale-export'])]
    #[CLI\Argument(name: 'langcode', description: 'The language code of the exported translations.')]
    #[CLI\Option(name: 'template', description: 'POT file output of extracted source texts to be translated.')]
    #[CLI\Option(name: 'types', description: 'A comma separated list of string types to include, defaults to all types. Recognized values: <info>not-customized</info>, <info>customized</info>, </info>not-translated<info>')]
    #[CLI\Usage(name: 'drush locale:export nl > nl.po', description: 'Export the Dutch translations with all types.')]
    #[CLI\Usage(name: 'drush locale:export nl --types=customized,not-customized > nl.po', description: 'Export the Dutch customized and not customized translations.')]
    #[CLI\Usage(name: 'drush locale:export --template > drupal.pot', description: 'Export the source strings only as template file for translation.')]
    #[CLI\ValidateModulesEnabled(modules: ['locale'])]
    public function export($langcode = null, $options = ['template' => false, 'types' => self::REQ]): void
    {
        $language = $this->getTranslatableLanguage($langcode);
        $poreader_options = [];

        if (!$options['template']) {
            $poreader_options = $this->convertTypesToPoDbReaderOptions(StringUtils::csvToArray($options['types']));
        }

        $file_uri = drush_tempnam('drush_', null, '.po');
        if ($this->writePoFile($file_uri, $language, $poreader_options)) {
            $this->output()->writeln(file_get_contents($file_uri));
        } else {
            $this->logger()->success(dt('Nothing to export.'));
        }
    }

    /**
     * Assure that required options are set.
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::EXPORT)]
    public function exportValidate(CommandData $commandData): void
    {
        $langcode = $commandData->input()->getArgument('langcode');
        $template = $commandData->input()->getOption('template');
        $types = $commandData->input()->getOption('types');

        if (!$langcode && !$template) {
            throw new CommandFailedException('Set LANGCODE or --template, see help for more information.');
        }
        if ($template && $types) {
            throw new CommandFailedException('Can not use both --types and --template, see help for more information.');
        }
    }

    /**
     * Imports multiple translation files from the defined directory.
     *
     * @throws \Exception
     */
    #[CLI\Command(name: self::IMPORT_ALL, aliases: ['locale-import-all', 'locale:import:all'])]
    #[CLI\Argument(name: 'directory', description: 'The path to directory with translation files to import.')]
    #[CLI\Option(name: 'type', description: 'String types to include, defaults to <info>not-customized</info>. Recognized values: <info>not-customized</info>, <info>customized</info>', suggestedValues: ['not-customized', 'customized'])]
    #[CLI\Option(name: 'override', description: 'Whether and how imported strings will override existing translations. Defaults to the Import behavior configured in the admin interface. Recognized values: <info>none</info>, <info>customized</info>, <info>not-customized</info>, <info>all</info>', suggestedValues: ['none', 'not-customized', 'customized', 'all'])]
    #[CLI\Usage(name: 'drush locale:import-all /var/www/translations', description: 'Import all translations from the defined directory (non-recursively). Supported filename patterns are: {project}-{version}.{langcode}.po, {prefix}.{langcode}.po or {langcode}.po.')]
    #[CLI\Usage(name: 'drush locale:import-all /var/www/translations/custom --types=customized --override=all', description: 'Import all custom translations from the defined directory (non-recursively) and override any existing translation. Supported filename patterns are: {project}-{version}.{langcode}.po, {prefix}.{langcode}.po or {langcode}.po.')]
    #[CLI\Version(version: '12.2')]
    #[CLI\ValidateModulesEnabled(modules: ['locale'])]
    public function importAll($directory, $options = ['type' => self::REQ, 'override' => self::REQ])
    {
        if (!is_dir($directory)) {
            throw new \Exception('The defined directory does not exist.');
        }

        // Look for .po files in defined directory
        $poFiles = glob($directory . DIRECTORY_SEPARATOR . '*.po');
        if (empty($poFiles)) {
            throw new \Exception('Translation files not found in the defined directory.');
        }

        $this->getModuleHandler()->loadInclude('locale', 'translation.inc');
        $this->getModuleHandler()->loadInclude('locale', 'bulk.inc');

        $translationOptions = _locale_translation_default_update_options();
        $translationOptions['customized'] = $this->convertCustomizedType($options['type']);
        $override = $this->convertOverrideOption($options['override']);
        if ($override) {
            $translationOptions['overwrite_options'] = $override;
        }
        $langcodes_to_import = [];
        $files = [];
        foreach ($poFiles as $file) {
            // Ensure we have the file intended for upload.
            if (!file_exists($file)) {
                $this->logger()->warning(dt('Can not read file @file.', ['@file' => $file]));
                continue;
            }
            $poFile = (object) [
                'filename' => basename($file),
                'uri' => $file,
            ];
            $poFile = locale_translate_file_attach_properties($poFile, $translationOptions);
            if ($poFile->langcode == LanguageInterface::LANGCODE_NOT_SPECIFIED) {
                $this->logger()->warning(dt('Can not autodetect language of file @file. Supported filename patterns are: {project}-{version}.{langcode}.po, {prefix}.{langcode}.po or {langcode}.po.', [
                   '@file' => $file,
                ]));
                continue;
            }
            if (!$this->getLanguageManager()->getLanguage($poFile->langcode)) {
                $this->logger()->warning(dt('Language @language does not exist for file @file', [
                    '@language' => $poFile->langcode,
                    '@file' => $file,
                ]));
                continue;
            }
            // Import translation file if language exists.
            $langcodes_to_import[$poFile->langcode] = $poFile->langcode;
            $files[$poFile->uri] = $poFile;
        }

        // Set a batch to download and import translations.
        $batch = locale_translate_batch_build($files, $translationOptions);
        batch_set($batch);
        if ($batch = locale_config_batch_update_components($translationOptions, $langcodes_to_import)) {
            batch_set($batch);
        }

        drush_backend_batch_process();
    }

    /**
     * Converts input of translation type.
     *
     * @param $type
     */
    private function convertCustomizedType($type): int
    {
        return $type == 'customized' ? LOCALE_CUSTOMIZED : LOCALE_NOT_CUSTOMIZED;
    }

    /**
     * Imports to a gettext translation file.
     */
    #[CLI\Command(name: self::IMPORT, aliases: ['locale-import'])]
    #[CLI\Argument(name: 'langcode', description: 'The language code of the imported translations.')]
    #[CLI\Argument(name: 'file', description: 'Path and file name of the gettext file. Relative paths calculated from Drupal root.')]
    #[CLI\Option(name: 'type', description: 'String types to include, defaults to all types. Recognized values: <info>not-customized</info>, <info>customized</info>, </info>not-translated<info>')]
    #[CLI\Option(name: 'override', description: 'Whether and how imported strings will override existing translations. Defaults to the Import behavior configured in the admin interface. Recognized values: <info>none</info>, <info>customized</info>, <info>not-customized</info>, <info>all</info>')]
    #[CLI\Option(name: 'autocreate-language', description: 'Create the language in addition to import.')]
    #[CLI\Usage(name: 'drush locale-import nl drupal-8.4.2.nl.po', description: 'Import the Dutch drupal core translation.')]
    #[CLI\Usage(name: 'drush locale-import --type=customized nl drupal-8.4.2.nl.po', description: 'Import the Dutch drupal core translation. Treat imported strings as custom translations.')]
    #[CLI\Usage(name: 'drush locale-import --override=none nl drupal-8.4.2.nl.po', description: "Import the Dutch drupal core translation. Don't overwrite existing translations. Only append new translations.")]
    #[CLI\Usage(name: 'drush locale-import --override=not-customized nl drupal-8.4.2.nl.po', description: 'Import the Dutch drupal core translation. Only override non-customized translations, customized translations are kept.')]
    #[CLI\Usage(name: 'drush locale-import nl custom-translations.po --type=customized --override=all', description: 'Import customized Dutch translations and override any existing translation.')]
    #[CLI\ValidateModulesEnabled(modules: ['locale'])]
    public function import($langcode, $file, $options = ['type' => 'not-customized', 'override' => self::REQ, 'autocreate-language' => false]): void
    {
        if (!drush_file_not_empty($file)) {
            throw new \Exception(dt('File @file not found or empty.', ['@file' => $file]));
        }

        $language = $this->getTranslatableLanguage($langcode, $options['autocreate-language']);

        $this->getModuleHandler()->loadInclude('locale', 'translation.inc');
        $this->getModuleHandler()->loadInclude('locale', 'bulk.inc');

        $translationOptions = _locale_translation_default_update_options();
        $translationOptions['langcode'] = $language->getId();
        $translationOptions['customized'] = $this->convertCustomizedType($options['type']);
        $override = $this->convertOverrideOption($options['override']);
        if ($override) {
            $translationOptions['overwrite_options'] = $override;
        }

        $poFile = (object) [
            'filename' => basename($file),
            'uri' => $file,
        ];
        $poFile = locale_translate_file_attach_properties($poFile, $translationOptions);

        // Set a batch to download and import translations.
        $batch = locale_translate_batch_build([$poFile->uri => $poFile], $translationOptions);
        batch_set($batch);
        if ($batch = locale_config_batch_update_components($translationOptions, [$language->getId()])) {
            batch_set($batch);
        }

        drush_backend_batch_process();
    }

    /**
     * Converts input of override option.
     *
     * @param $override
     */
    private function convertOverrideOption($override): array
    {
        $result = [];

        switch ($override) {
            case 'none':
                $result = [
                    'not_customized' => false,
                    'customized' => false,
                ];
                break;

            case 'customized':
                $result = [
                    'not_customized' => false,
                    'customized' => true,
                ];
                break;

            case 'not-customized':
                $result = [
                    'not_customized' => true,
                    'customized' => false,
                ];
                break;

            case 'all':
                $result = [
                    'not_customized' => true,
                    'customized' => true,
                ];
                break;
        }

        return $result;
    }

    /**
     * Get translatable language object.
     *
     * @param string $langcode The language code of the language object.
     * @param bool $addLanguage Create language when not available.
     * @return LanguageInterface|null
     * @throws \Exception
     */
    private function getTranslatableLanguage(string $langcode, bool $addLanguage = false)
    {
        if (!$langcode) {
            return null;
        }

        $language = $this->getLanguageManager()->getLanguage($langcode);

        if (!$language) {
            if ($addLanguage) {
                $language = ConfigurableLanguage::createFromLangcode($langcode);
                $language->save();

                $this->logger()->success(dt('Added language @language', [
                    '@language' => $language->label(),
                ]));
            } else {
                throw new CommandFailedException(dt('Language code @langcode is not configured.', [
                    '@langcode' => $langcode,
                ]));
            }
        }

        if (!$this->isTranslatable($language)) {
            throw new CommandFailedException(dt('Language code @langcode is not translatable.', [
                '@langcode' => $langcode,
            ]));
        }

        return $language;
    }

    /**
     * Check if language is translatable.
     *
     * @param LanguageInterface $language
     */
    private function isTranslatable(LanguageInterface $language): bool
    {
        if ($language->isLocked()) {
            return false;
        }

        if ($language->getId() != 'en') {
            return true;
        }

        return (bool)$this->getConfigFactory()
            ->get('locale.settings')
            ->get('translate_english');
    }

    /**
     * Get PODatabaseReader options for given types.
     *
     * @param array $types
     *   Options list with value 'true'.
     * @throws \Exception
     *   Triggered with incorrect types.
     */
    private function convertTypesToPoDbReaderOptions(array $types = []): array
    {
        $valid_convertions = [
            'not_customized' => 'not-customized',
            'customized' => 'customized',
            'not_translated' => 'not-translated',
        ];

        if (empty($types)) {
            return array_fill_keys(array_keys($valid_convertions), true);
        }

        // Check for invalid conversions.
        if (array_diff($types, $valid_convertions)) {
            throw new CommandFailedException(dt('Allowed types: @types.', [
                '@types' => implode(', ', $valid_convertions),
            ]));
        }

        // Convert Types to Options.
        $options = array_keys(array_intersect($valid_convertions, $types));

        return array_fill_keys($options, true);
    }

    /**
     * Write out the exported language or template file.
     *
     * @param string $file_uri Uri string to gather the data.
     * @param LanguageInterface|null $language The language to export.
     * @param array $options The export options for PoDatabaseReader.
     * @return bool True if successful.
     */
    private function writePoFile(string $file_uri, LanguageInterface $language = null, array $options = []): bool
    {
        $reader = new PoDatabaseReader();

        if ($language) {
            $reader->setLangcode($language->getId());
            $reader->setOptions($options);
        }

        $reader_item = $reader->readItem();
        if (empty($reader_item)) {
            return false;
        }

        $header = $reader->getHeader();
        $header->setProjectName($this->configFactory->get('system.site')->get('name'));
        $language_name = ($language) ? $language->getName() : '';
        $header->setLanguageName($language_name);

        $writer = new PoStreamWriter();
        $writer->setURI($file_uri);
        $writer->setHeader($header);
        $writer->open();
        $writer->writeItem($reader_item);
        $writer->writeItems($reader);
        $writer->close();

        return true;
    }
}
