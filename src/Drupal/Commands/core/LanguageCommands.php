<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;

class LanguageCommands extends DrushCommands
{

    /**
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $languageManager;

    /**
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * @return \Drupal\Core\Language\LanguageManagerInterface
     */
    public function getLanguageManager()
    {
        return $this->languageManager;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleHandlerInterface
     */
    public function getModuleHandler()
    {
        return $this->moduleHandler;
    }

    public function __construct(LanguageManagerInterface $languageManager, ModuleHandlerInterface $moduleHandler)
    {
        $this->languageManager = $languageManager;
        $this->moduleHandler = $moduleHandler;
    }

    /**
     * Add a configurable language.
     *
     * @command language:add
     * @param $langcode A comma delimited list of language codes.
     * @option skip-translations Prevent translations to be downloaded and/or imported.
     * @usage drush language:add nl,fr
     *   Add Dutch and French language and import their translations.
     * @usage drush language:add nl --skip-translations
     *   Add Dutch language without importing translations.
     * @aliases language-add
     * @validate-module-enabled language
     * @hidden
     * @throws \Exception
     */
    public function add($langcode, $options = ['skip-translations' => false])
    {
        if ($langcodes = StringUtils::csvToArray($langcode)) {
            $langcodes = array_unique($langcodes);
            $langcodes = $this->filterValidLangcode($langcodes);
            $langcodes = $this->filterNewLangcode($langcodes);
            if (empty($langcodes)) {
                return;
            }

            foreach ($langcodes as $langcode) {
                $language = ConfigurableLanguage::createFromLangcode($langcode);
                $language->save();

                $this->logger->success(dt('Added language @language', [
                    '@language' => $language->label(),
                ]));
            }

            if ($options['skip-translations']) {
                return;
            }

            if ($this->getModuleHandler()->moduleExists('locale')) {
                $this->setBatchLanguageImport($langcodes);
                drush_backend_batch_process();
            }
        }
    }

    /**
     * Print the currently available languages.
     *
     * @command language:info
     * @aliases language-info
     * @hidden
     * @field-labels
     *   language: Language
     *   direction: Direction
     *   default: Default
     *   locked: Locked
     * @default-fields language,direction,default
     * @filter-default-field language
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function info()
    {
        $rows = [];
        $languages = $this->getLanguageManager()->getLanguages();

        foreach ($languages as $key => $language) {
            $row = [
                'language' => $language->getName() . ' (' . $language->getId() . ')',
                'direction' => $language->getDirection(),
                'default' => $language->isDefault() ? dt('yes') : '',
                'locked' => $language->isLocked() ? dt('yes') : '',
            ];
            $rows[$key] = $row;
        }

        return new RowsOfFields($rows);
    }

    /**
     * Filters valid language codes.
     *
     * @param $langcodes
     * @return array
     * @throws \Exception
     *   Exception when a language code is not in the standard language list.
     */
    private function filterValidLangcode($langcodes)
    {
        $standardLanguages = $this->getLanguageManager()->getStandardLanguageList();
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
     *
     * @param $langcodes
     * @return array
     */
    private function filterNewLangcode($langcodes)
    {
        $enabledLanguages = $this->getLanguageManager()->getLanguages();
        foreach ($langcodes as $key => $langcode) {
            if (isset($enabledLanguages[$langcode])) {
                $this->logger->warning(dt('The language !langcode is already enabled.', [
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
    private function setBatchLanguageImport($langcodes)
    {
        $moduleHandler = $this->getModuleHandler();
        $moduleHandler->loadInclude('locale', 'inc', 'locale.translation');
        $moduleHandler->loadInclude('locale', 'inc', 'locale.fetch');
        $moduleHandler->loadInclude('locale', 'inc', 'locale.bulk');
        $translationOptions = _locale_translation_default_update_options();

        locale_translation_clear_status();
        $batch = locale_translation_batch_update_build([], $langcodes, $translationOptions);
        batch_set($batch);
        if ($batch = locale_config_batch_update_components($translationOptions, $langcodes)) {
            batch_set($batch);
        }
    }
}
