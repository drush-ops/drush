<?php

namespace Drupal\locale\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Return response for manual check translations.
 */
class LocaleController extends ControllerBase {

  /**
   * Checks for translation updates and displays the translations status.
   *
   * Manually checks the translation status without the use of cron.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirection to translations reports page.
   */
  public function checkTranslation() {
    $this->moduleHandler()->loadInclude('locale', 'inc', 'locale.compare');

    // Check translation status of all translatable project in all languages.
    // First we clear the cached list of projects. Although not strictly
    // necessary, this is helpful in case the project list is out of sync.
    locale_translation_flush_projects();
    locale_translation_check_projects();

    // Execute a batch if required. A batch is only used when remote files
    // are checked.
    if (batch_get()) {
      return batch_process('admin/reports/translations');
    }

    return $this->redirect('locale.translate_status');
  }

  /**
   * Shows the string search screen.
   *
   * @return array
   *   The render array for the string search screen.
   */
  public function translatePage() {
    return [
      'filter' => $this->formBuilder()->getForm('Drupal\locale\Form\TranslateFilterForm'),
      'form' => $this->formBuilder()->getForm('Drupal\locale\Form\TranslateEditForm'),
    ];
  }

}
