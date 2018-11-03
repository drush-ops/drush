<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\views\ResultRow;

/**
 * Render a field as a numeric value
 *
 * Definition terms:
 * - float: If true this field contains a decimal value. If unset this field
 *          will be assumed to be integer.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("numeric")
 */
class NumericField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['set_precision'] = ['default' => FALSE];
    $options['precision'] = ['default' => 0];
    $options['decimal'] = ['default' => '.'];
    $options['separator'] = ['default' => ','];
    $options['format_plural'] = ['default' => FALSE];
    $options['format_plural_string'] = ['default' => '1' . LOCALE_PLURAL_DELIMITER . '@count'];
    $options['prefix'] = ['default' => ''];
    $options['suffix'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    if (!empty($this->definition['float'])) {
      $form['set_precision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Round'),
        '#description' => $this->t('If checked, the number will be rounded.'),
        '#default_value' => $this->options['set_precision'],
      ];
      $form['precision'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Precision'),
        '#default_value' => $this->options['precision'],
        '#description' => $this->t('Specify how many digits to print after the decimal point.'),
        '#states' => [
          'visible' => [
            ':input[name="options[set_precision]"]' => ['checked' => TRUE],
          ],
        ],
        '#size' => 2,
      ];
      $form['decimal'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Decimal point'),
        '#default_value' => $this->options['decimal'],
        '#description' => $this->t('What single character to use as a decimal point.'),
        '#size' => 2,
      ];
    }
    $form['separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Thousands marker'),
      '#options' => [
        '' => $this->t('- None -'),
        ',' => $this->t('Comma'),
        ' ' => $this->t('Space'),
        '.' => $this->t('Decimal'),
        '\'' => $this->t('Apostrophe'),
      ],
      '#default_value' => $this->options['separator'],
      '#description' => $this->t('What single character to use as the thousands separator.'),
      '#size' => 2,
    ];
    $form['format_plural'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Format plural'),
      '#description' => $this->t('If checked, special handling will be used for plurality.'),
      '#default_value' => $this->options['format_plural'],
    ];
    $form['format_plural_string'] = [
      '#type' => 'value',
      '#default_value' => $this->options['format_plural_string'],
    ];

    $plural_array = explode(LOCALE_PLURAL_DELIMITER, $this->options['format_plural_string']);
    $plurals = $this->getNumberOfPlurals($this->view->storage->get('langcode'));
    for ($i = 0; $i < $plurals; $i++) {
      $form['format_plural_values'][$i] = [
        '#type' => 'textfield',
        // @todo Should use better labels https://www.drupal.org/node/2499639
        '#title' => ($i == 0 ? $this->t('Singular form') : $this->formatPlural($i, 'First plural form', '@count. plural form')),
        '#default_value' => isset($plural_array[$i]) ? $plural_array[$i] : '',
        '#description' => $this->t('Text to use for this variant, @count will be replaced with the value.'),
        '#states' => [
          'visible' => [
            ':input[name="options[format_plural]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    if ($plurals == 2) {
      // Simplify interface text for the most common case.
      $form['format_plural_values'][0]['#description'] = $this->t('Text to use for the singular form, @count will be replaced with the value.');
      $form['format_plural_values'][1]['#title'] = $this->t('Plural form');
      $form['format_plural_values'][1]['#description'] = $this->t('Text to use for the plural form, @count will be replaced with the value.');
    }

    $form['prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix'),
      '#default_value' => $this->options['prefix'],
      '#description' => $this->t('Text to put before the number, such as currency symbol.'),
    ];
    $form['suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix'),
      '#default_value' => $this->options['suffix'],
      '#description' => $this->t('Text to put after the number, such as currency symbol.'),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    // Merge plural format options into one string and drop the individual
    // option values.
    $options = &$form_state->getValue('options');
    $options['format_plural_string'] = implode(LOCALE_PLURAL_DELIMITER, $options['format_plural_values']);
    unset($options['format_plural_values']);
    parent::submitOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    // Check to see if hiding should happen before adding prefix and suffix
    // and before rewriting.
    if ($this->options['hide_empty'] && empty($value) && ($value !== 0 || $this->options['empty_zero'])) {
      return '';
    }

    if (!empty($this->options['set_precision'])) {
      $precision = $this->options['precision'];
    }
    elseif ($decimal_position = strpos($value, '.')) {
      $precision = strlen($value) - $decimal_position - 1;
    }
    else {
      $precision = 0;
    }

    // Use round first to avoid negative zeros.
    $value = round($value, $precision);
    // Test against both integer zero and float zero.
    if ($this->options['empty_zero'] && ($value === 0 || $value === 0.0)) {
      return '';
    }

    $value = number_format($value, $precision, $this->options['decimal'], $this->options['separator']);

    // If we should format as plural, take the (possibly) translated plural
    // setting and format with the current language.
    if (!empty($this->options['format_plural'])) {
      $value = PluralTranslatableMarkup::createFromTranslatedString($value, $this->options['format_plural_string']);
    }

    return $this->sanitizeValue($this->options['prefix'], 'xss')
      . $this->sanitizeValue($value)
      . $this->sanitizeValue($this->options['suffix'], 'xss');
  }

}
