<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;

/**
 * Field handler to show data of serialized fields.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("serialized")
 */
class Serialized extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['format'] = ['default' => 'unserialized'];
    $options['key'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Display format'),
      '#description' => $this->t('How should the serialized data be displayed. You can choose a custom array/object key or a print_r on the full output.'),
      '#options' => [
        'unserialized' => $this->t('Full data (unserialized)'),
        'serialized' => $this->t('Full data (serialized)'),
        'key' => $this->t('A certain key'),
      ],
      '#default_value' => $this->options['format'],
    ];
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Which key should be displayed'),
      '#default_value' => $this->options['key'],
      '#states' => [
        'visible' => [
          ':input[name="options[format]"]' => ['value' => 'key'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    // Require a key if the format is key.
    if ($form_state->getValue(['options', 'format']) == 'key' && $form_state->getValue(['options', 'key']) == '') {
      $form_state->setError($form['key'], $this->t('You have to enter a key if you want to display a key of the data.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $values->{$this->field_alias};

    if ($this->options['format'] == 'unserialized') {
      return $this->sanitizeValue(print_r(unserialize($value), TRUE));
    }
    elseif ($this->options['format'] == 'key' && !empty($this->options['key'])) {
      $value = (array) unserialize($value);
      return $this->sanitizeValue($value[$this->options['key']]);
    }

    return $value;
  }

}
