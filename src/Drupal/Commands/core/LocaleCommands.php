<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\Commands\DrushCommands;

class LocaleCommands extends DrushCommands
{

    protected $languageManager;

    protected $configFactory;

    protected $moduleHandler;

    protected $state;

    /**
     * @return \Drupal\Core\Language\LanguageManagerInterface
     */
    protected function getLanguageManager()
    {
        return $this->languageManager;
    }

    /**
     * @return \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected function getConfigFactory()
    {
        return $this->configFactory;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleHandlerInterface
     */
    public function getModuleHandler()
    {
        return $this->moduleHandler;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    public function __construct(LanguageManagerInterface $languageManager, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, StateInterface $state)
    {
        $this->languageManager = $languageManager;
        $this->configFactory = $configFactory;
        $this->moduleHandler = $moduleHandler;
        $this->state = $state;
    }

    /**
     * Checks for available translation updates.
     *
     * @command locale:check
     * @aliases locale-check
     * @validate-module-enabled locale
     */
    public function check()
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
     * Imports the available translation updates.
     *
     * @see TranslationStatusForm::buildForm()
     * @see TranslationStatusForm::prepareUpdateData()
     * @see TranslationStatusForm::submitForm()
     *
     * @todo This can be simplified once https://www.drupal.org/node/2631584 lands
     *   in Drupal core.
     *
     * @command locale:update
     * @aliases locale-update
     * @option langcodes A comma-separated list of language codes to update. If omitted, all translations will be updated.
     * @validate-module-enabled locale
     */
    public function update($options = ['langcodes' => self::REQ])
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
            // @todo Not selecting any language code in the user interface results in
            //   all translations being updated, so we mimick that behavior here.
        }

        // Deduplicate the list of langcodes since each project may have added the
        // same language several times.
        $langcodes = array_unique($langcodes);

        // @todo Restricting by projects is not possible in the user interface and is
        //   broken when attempting to do it in a hook_form_alter() implementation so
        //   we do not allow for it here either.
        $projects = [];

        // Set the translation import options. This determines if existing
        // translations will be overwritten by imported strings.
        $translationOptions = _locale_translation_default_update_options();

        // If the status was updated recently we can immediately start fetching the
        // translation updates. If the status is expired we clear it an run a batch to
        // update the status and then fetch the translation updates.
        $last_checked = $this->getState()->get('locale.translation_last_checked');
        if ($last_checked < REQUEST_TIME - LOCALE_TRANSLATION_STATUS_TTL) {
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
     * Imports to a gettext translation file.
     *
     * @command locale:import
     * @validate-module-enabled locale
     * @param $langcode The language code of the imported translations.
     * @param $file Path and file name of the gettext file.
     * @option type The type of translations to be imported, defaults to 'not-customized'. Options:
     *   - customized: Treat imported strings as custom translations.
     *   - not-customized: Treat imported strings as not-custom translations.
     * @option override Whether and how imported strings will override existing translations. Defaults to the Import behavior configurred in the admin interface. Options:
     *  - none: Don't overwrite existing translations. Only append new translations.
     *  - customized: Only override existing customized translations.
     *  - not-customized: Only override non-customized translations, customized translations are kept.
     *  - all: Override any existing translation.
     * @usage drush locale-import nl drupal-8.4.2.nl.po
     *   Import the Dutch drupal core translation.
     * @usage drush locale-import nl custom-translations.po --type=custom --override=all
     *   Import customized Dutch translations and override any existing translation.
     * @aliases locale-import
     * @throws \Exception
     */
    public function import($langcode, $file, $options = ['type' => self::OPT, 'override' => self::OPT])
    {
        if (!drush_file_not_empty($file)) {
            throw new \Exception(dt('File @file not found or empty.', ['@file' => $file]));
        }

        $language = $this->getTranslatableLanguage($langcode, true);

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
     * Converts input of translation type.
     *
     * @param $type
     * @return integer
     */
    private function convertCustomizedType($type)
    {
        switch ($type) {
            case 'customized':
                $result = LOCALE_CUSTOMIZED;
                break;

            default:
                $result = LOCALE_NOT_CUSTOMIZED;
                break;
        }

        return $result;
    }

    /**
     * Converts input of override option.
     *
     * @param $override
     * @return array
     */
    private function convertOverrideOption($override)
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
    private function getTranslatableLanguage($langcode, $addLanguage = false)
    {
        if (!$langcode) {
            return null;
        }

        $language = $this->getLanguageManager()->getLanguage($langcode);

        if (!$language) {
            if ($addLanguage) {
                $language = ConfigurableLanguage::createFromLangcode($langcode);
                $language->save();

                $this->logger->success(dt('Added language @language', [
                    '@language' => $language->label(),
                ]));
            } else {
                throw new \Exception(dt('Language code @langcode is not configured.', [
                    '@langcode' => $langcode,
                ]));
            }
        }

        if (!$this->isTranslatable($language)) {
            throw new \Exception(dt('Language code @langcode is not translatable.', [
                '@langcode' => $langcode,
            ]));
        }

        return $language;
    }

    /**
     * Check if language is translatable.
     *
     * @param LanguageInterface $language
     * @return bool
     */
    private function isTranslatable(LanguageInterface $language)
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
}
