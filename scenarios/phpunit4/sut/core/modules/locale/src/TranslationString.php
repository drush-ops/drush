<?php

namespace Drupal\locale;

/**
 * Defines the locale translation string object.
 *
 * This class represents a translation of a source string to a given language,
 * thus it must have at least a 'language' which is the language code and a
 * 'translation' property which is the translated text of the source string
 * in the specified language.
 */
class TranslationString extends StringBase {
  /**
   * The language code.
   *
   * @var string
   */
  public $language;

  /**
   * The string translation.
   *
   * @var string
   */
  public $translation;

  /**
   * Integer indicating whether this string is customized.
   *
   * @var int
   */
  public $customized;

  /**
   * Boolean indicating whether the string object is new.
   *
   * @var bool
   */
  protected $isNew;

  /**
   * {@inheritdoc}
   */
  public function __construct($values = []) {
    parent::__construct($values);
    if (!isset($this->isNew)) {
      // We mark the string as not new if it is a complete translation.
      // This will work when loading from database, otherwise the storage
      // controller that creates the string object must handle it.
      $this->isNew = !$this->isTranslation();
    }
  }

  /**
   * Sets the string as customized / not customized.
   *
   * @param bool $customized
   *   (optional) Whether the string is customized or not. Defaults to TRUE.
   *
   * @return \Drupal\locale\TranslationString
   *   The called object.
   */
  public function setCustomized($customized = TRUE) {
    $this->customized = $customized ? LOCALE_CUSTOMIZED : LOCALE_NOT_CUSTOMIZED;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isSource() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isTranslation() {
    return !empty($this->lid) && !empty($this->language) && isset($this->translation);
  }

  /**
   * {@inheritdoc}
   */
  public function getString() {
    return isset($this->translation) ? $this->translation : '';
  }

  /**
   * {@inheritdoc}
   */
  public function setString($string) {
    $this->translation = $string;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNew() {
    return $this->isNew;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    parent::save();
    $this->isNew = FALSE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    $this->isNew = TRUE;
    return $this;
  }

}
