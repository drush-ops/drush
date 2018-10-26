<?php

namespace Drupal\webprofiler\StringTranslation;

use Drupal\Core\StringTranslation\TranslationManager;

/**
 * Class TranslationManagerWrapper
 */
class TranslationManagerWrapper extends TranslationManager {

  /**
   * @var \Drupal\webprofiler\StringTranslation\TranslationManagerWrapper
   */
  private $translationManager;

  /**
   * @var array
   */
  private $translated;

  /**
   * @var array
   */
  private $untranslated;

  /**
   * @param \Drupal\webprofiler\StringTranslation\TranslationManagerWrapper $translationManager
   */
  public function setDataCollector(TranslationManagerWrapper $translationManager) {
    $this->translationManager = $translationManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTranslate($string, array $options = array()) {
    // Merge in defaults.
    if (empty($options['langcode'])) {
      $options['langcode'] = $this->defaultLangcode;
    }
    if (empty($options['context'])) {
      $options['context'] = '';
    }
    $translation = $this->getStringTranslation($options['langcode'], $string, $options['context']);

    if($translation) {
      $this->translated[$string] = $translation;
    } else {
      $this->untranslated[$string] = $string;
    }

    return $translation === FALSE ? $string : $translation;
  }

  /**
   * @return array
   */
  public function getTranslated() {
    return $this->translated;
  }

  /**
   * @return array
   */
  public function getUntranslated() {
    return $this->untranslated;
  }
}
