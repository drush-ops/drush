<?php

namespace Drush\Drupal\Commands\core;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;

class LocaleCommands extends DrushCommands
{

    protected $moduleHandler;

    protected $state;

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

    public function __construct(ModuleHandlerInterface $moduleHandler, StateInterface $state)
    {
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

        if ($passed_langcodes = $translationOptions['langcodes']) {
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
}
