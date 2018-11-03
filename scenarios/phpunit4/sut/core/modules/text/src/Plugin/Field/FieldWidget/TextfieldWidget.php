<?php

namespace Drupal\text\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Plugin implementation of the 'text_textfield' widget.
 *
 * @FieldWidget(
 *   id = "text_textfield",
 *   label = @Translation("Text field"),
 *   field_types = {
 *     "text"
 *   },
 * )
 */
class TextfieldWidget extends StringTextfieldWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $main_widget = parent::formElement($items, $delta, $element, $form, $form_state);

    $element = $main_widget['value'];
    $element['#type'] = 'text_format';
    $element['#format'] = isset($items[$delta]->format) ? $items[$delta]->format : NULL;
    $element['#base_type'] = $main_widget['value']['#type'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    if ($violation->arrayPropertyPath == ['format'] && isset($element['format']['#access']) && !$element['format']['#access']) {
      // Ignore validation errors for formats if formats may not be changed,
      // i.e. when existing formats become invalid. See filter_process_format().
      return FALSE;
    }
    return $element;
  }

}
