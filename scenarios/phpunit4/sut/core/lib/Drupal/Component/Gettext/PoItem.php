<?php

namespace Drupal\Component\Gettext;

/**
 * PoItem handles one translation.
 *
 * @todo: This class contains some really old legacy code.
 * @see https://www.drupal.org/node/1637662
 */
class PoItem {

  /**
   * The language code this translation is in.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The context this translation belongs to.
   *
   * @var string
   */
  protected $context = '';

  /**
   * The source string or array of strings if it has plurals.
   *
   * @var string|array
   *
   * @see $plural
   */
  protected $source;

  /**
   * Flag indicating if this translation has plurals.
   *
   * @var bool
   */
  protected $plural;

  /**
   * The comment of this translation.
   *
   * @var string
   */
  protected $comment;

  /**
   * The translation string or array of strings if it has plurals.
   *
   * @var string|array
   * @see $plural
   */
  protected $translation;

  /**
   * Gets the language code of the currently used language.
   *
   * @return string with langcode
   */
  public function getLangcode() {
    return $this->langcode;
  }

  /**
   * Set the language code of the current language.
   *
   * @param string $langcode
   */
  public function setLangcode($langcode) {
    $this->langcode = $langcode;
  }

  /**
   * Gets the context this translation belongs to.
   *
   * @return string $context
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Set the context this translation belongs to.
   *
   * @param string $context
   */
  public function setContext($context) {
    $this->context = $context;
  }

  /**
   * Gets the source string or the array of strings if the translation has
   * plurals.
   *
   * @return string or array $translation
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * Set the source string or the array of strings if the translation has
   * plurals.
   *
   * @param string|array $source
   */
  public function setSource($source) {
    $this->source = $source;
  }

  /**
   * Gets the translation string or the array of strings if the translation has
   * plurals.
   *
   * @return string or array $translation
   */
  public function getTranslation() {
    return $this->translation;
  }

  /**
   * Set the translation string or the array of strings if the translation has
   * plurals.
   *
   * @param string|array $translation
   */
  public function setTranslation($translation) {
    $this->translation = $translation;
  }

  /**
   * Set if the translation has plural values.
   *
   * @param bool $plural
   */
  public function setPlural($plural) {
    $this->plural = $plural;
  }

  /**
   * Get if the translation has plural values.
   *
   * @return bool
   */
  public function isPlural() {
    return $this->plural;
  }

  /**
   * Gets the comment of this translation.
   *
   * @return String $comment
   */
  public function getComment() {
    return $this->comment;
  }

  /**
   * Set the comment of this translation.
   *
   * @param string $comment
   */
  public function setComment($comment) {
    $this->comment = $comment;
  }

  /**
   * Create the PoItem from a structured array.
   *
   * @param array $values
   */
  public function setFromArray(array $values = []) {
    if (isset($values['context'])) {
      $this->setContext($values['context']);
    }
    if (isset($values['source'])) {
      $this->setSource($values['source']);
    }
    if (isset($values['translation'])) {
      $this->setTranslation($values['translation']);
    }
    if (isset($values['comment'])) {
      $this->setComment($values['comment']);
    }
    if (isset($this->source) &&
        strpos($this->source, LOCALE_PLURAL_DELIMITER) !== FALSE) {
      $this->setSource(explode(LOCALE_PLURAL_DELIMITER, $this->source));
      $this->setTranslation(explode(LOCALE_PLURAL_DELIMITER, $this->translation));
      $this->setPlural(count($this->source) > 1);
    }
  }

  /**
   * Output the PoItem as a string.
   */
  public function __toString() {
    return $this->formatItem();
  }

  /**
   * Format the POItem as a string.
   */
  private function formatItem() {
    $output = '';

    // Format string context.
    if (!empty($this->context)) {
      $output .= 'msgctxt ' . $this->formatString($this->context);
    }

    // Format translation.
    if ($this->plural) {
      $output .= $this->formatPlural();
    }
    else {
      $output .= $this->formatSingular();
    }

    // Add one empty line to separate the translations.
    $output .= "\n";

    return $output;
  }

  /**
   * Formats a plural translation.
   */
  private function formatPlural() {
    $output = '';

    // Format source strings.
    $output .= 'msgid ' . $this->formatString($this->source[0]);
    $output .= 'msgid_plural ' . $this->formatString($this->source[1]);

    foreach ($this->translation as $i => $trans) {
      if (isset($this->translation[$i])) {
        $output .= 'msgstr[' . $i . '] ' . $this->formatString($trans);
      }
      else {
        $output .= 'msgstr[' . $i . '] ""' . "\n";
      }
    }

    return $output;
  }

  /**
   * Formats a singular translation.
   */
  private function formatSingular() {
    $output = '';
    $output .= 'msgid ' . $this->formatString($this->source);
    $output .= 'msgstr ' . (isset($this->translation) ? $this->formatString($this->translation) : '""');
    return $output;
  }

  /**
   * Formats a string for output on multiple lines.
   */
  private function formatString($string) {
    // Escape characters for processing.
    $string = addcslashes($string, "\0..\37\\\"");

    // Always include a line break after the explicit \n line breaks from
    // the source string. Otherwise wrap at 70 chars to accommodate the extra
    // format overhead too.
    $parts = explode("\n", wordwrap(str_replace('\n', "\\n\n", $string), 70, " \n"));

    // Multiline string should be exported starting with a "" and newline to
    // have all lines aligned on the same column.
    if (count($parts) > 1) {
      return "\"\"\n\"" . implode("\"\n\"", $parts) . "\"\n";
    }
    // Single line strings are output on the same line.
    else {
      return "\"$parts[0]\"\n";
    }
  }

}
