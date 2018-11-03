<?php

namespace Drupal\language\Config;

use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageDefault;

/**
 * Defines the interface for a configuration factory language override object.
 */
interface LanguageConfigFactoryOverrideInterface extends ConfigFactoryOverrideInterface {

  /**
   * Gets the language object used to override configuration data.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The language object used to override configuration data.
   */
  public function getLanguage();

  /**
   * Sets the language to be used in configuration overrides.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object used to override configuration data.
   *
   * @return $this
   */
  public function setLanguage(LanguageInterface $language = NULL);

  /**
   * Sets the language to be used in configuration overrides from the default.
   *
   * @param \Drupal\Core\Language\LanguageDefault $language_default
   *   The default language.
   *
   * @return $this
   *
   * @deprecated in Drupal 8.3.x and will be removed before Drupal 9.0.0. This
   *   method has been replaced by injecting the default language into the
   *   constructor.
   */
  public function setLanguageFromDefault(LanguageDefault $language_default = NULL);

  /**
   * Get language override for given language and configuration name.
   *
   * @param string $langcode
   *   Language code.
   * @param string $name
   *   Configuration name.
   *
   * @return \Drupal\Core\Config\Config
   *   Configuration override object.
   */
  public function getOverride($langcode, $name);

  /**
   * Returns the storage instance for a particular langcode.
   *
   * @param string $langcode
   *   Language code.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The storage instance for a particular langcode.
   */
  public function getStorage($langcode);

  /**
   * Installs available language configuration overrides for a given langcode.
   *
   * @param string $langcode
   *   Language code.
   */
  public function installLanguageOverrides($langcode);

}
