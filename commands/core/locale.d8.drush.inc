<?php

/**
 * @file
 * Provides Drush commands related to Interface Translation.
 */

/**
 * Implementation of hook_drush_help().
 */
function locale_drush_help($section) {
  switch ($section) {
    case 'meta:locale:title':
      return dt('Interface translation');
    case 'meta:locale:summary':
      return dt('Interact with the interface translation system.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function locale_drush_command() {
  $items['locale-check'] = [
    'description' => 'Checks for available translation updates.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  ];
  $items['locale-update'] = [
    'description' => 'Updates the available translations.',
    'options' => [
      'langcodes' => 'A comma-separated list of language codes to update. If omitted, all translations will be updated.'
    ],
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  ];
  // @todo Implement proper export and import commands.
  return $items;
}

/**
 * Checks for available translation updates.
 *
 * @see \Drupal\locale\Controller\LocaleController::checkTranslation()
 *
 * @todo This can be simplified once https://www.drupal.org/node/2631584 lands
 *   in Drupal core.
 */
function drush_locale_check() {
  \Drupal::moduleHandler()->loadInclude('locale', 'inc', 'locale.compare');

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
 */
function drush_locale_update() {
  $module_handler = \Drupal::moduleHandler();
  $module_handler->loadInclude('locale', 'fetch.inc');
  $module_handler->loadInclude('locale', 'bulk.inc');

  $langcodes = [];
  foreach (locale_translation_get_status() as $project_id => $project) {
    foreach ($project as $langcode => $project_info) {
      if (!empty($project_info->type)) {
        $langcodes[] = $langcode;
      }
    }
  }

  if ($passed_langcodes = drush_get_option('langcodes')) {
    $langcodes = array_intersect($langcodes, explode(',', $passed_langcodes));
    // @todo Not selecting any language code in the user interface results in
    //   all translations being updated, so we mimick that behavior here.
  }
  // @todo Restricting by projects is not possible in the user interface and is
  //   broken when attempting to do it in a hook_form_alter() implementation so
  //   we do not allow for it here either.
  $projects = [];

  // Set the translation import options. This determines if existing
  // translations will be overwritten by imported strings.
  $options = _locale_translation_default_update_options();

  // If the status was updated recently we can immediately start fetching the
  // translation updates. If the status is expired we clear it an run a batch to
  // update the status and then fetch the translation updates.
  $last_checked = \Drupal::state()->get('locale.translation_last_checked');
  if ($last_checked < REQUEST_TIME - LOCALE_TRANSLATION_STATUS_TTL) {
    locale_translation_clear_status();
    $batch = locale_translation_batch_update_build(array(), $langcodes, $options);
    batch_set($batch);
  }
  else {
    // Set a batch to download and import translations.
    $batch = locale_translation_batch_fetch_build($projects, $langcodes, $options);
    batch_set($batch);
    // Set a batch to update configuration as well.
    if ($batch = locale_config_batch_update_components($options, $langcodes)) {
      batch_set($batch);
    }
  }

  drush_backend_batch_process();
}
