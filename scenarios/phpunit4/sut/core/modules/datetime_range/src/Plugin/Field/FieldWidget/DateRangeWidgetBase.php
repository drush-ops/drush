<?php

namespace Drupal\datetime_range\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;

/**
 * Base class for the 'daterange_*' widgets.
 */
class DateRangeWidgetBase extends DateTimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Wrap all of the select elements with a fieldset.
    $element['#theme_wrappers'][] = 'fieldset';

    $element['#element_validate'][] = [$this, 'validateStartEnd'];
    $element['value']['#title'] = $this->t('Start date');

    $element['end_value'] = [
      '#title' => $this->t('End date'),
    ] + $element['value'];

    if ($items[$delta]->start_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $items[$delta]->start_date;
      $element['value']['#default_value'] = $this->createDefaultValue($start_date, $element['value']['#date_timezone']);
    }

    if ($items[$delta]->end_date) {
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $items[$delta]->end_date;
      $element['end_value']['#default_value'] = $this->createDefaultValue($end_date, $element['end_value']['#date_timezone']);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // The widget form element type has transformed the value to a
    // DrupalDateTime object at this point. We need to convert it back to the
    // storage timezone and format.
    foreach ($values as &$item) {
      if (!empty($item['value']) && $item['value'] instanceof DrupalDateTime) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
        $start_date = $item['value'];
        switch ($this->getFieldSetting('datetime_type')) {
          case DateRangeItem::DATETIME_TYPE_DATE:
            $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
            break;

          case DateRangeItem::DATETIME_TYPE_ALLDAY:
            // All day fields start at midnight on the starting date, but are
            // stored like datetime fields, so we need to adjust the time.
            // This function is called twice, so to prevent a double conversion
            // we need to explicitly set the timezone.
            $start_date->setTimeZone(timezone_open(drupal_get_user_timezone()));
            $start_date->setTime(0, 0, 0);
            $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
            break;

          default:
            $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
            break;
        }
        // Adjust the date for storage.
        $start_date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $item['value'] = $start_date->format($format);
      }

      if (!empty($item['end_value']) && $item['end_value'] instanceof DrupalDateTime) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
        $end_date = $item['end_value'];
        switch ($this->getFieldSetting('datetime_type')) {
          case DateRangeItem::DATETIME_TYPE_DATE:
            $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
            break;

          case DateRangeItem::DATETIME_TYPE_ALLDAY:
            // All day fields end at midnight on the end date, but are
            // stored like datetime fields, so we need to adjust the time.
            // This function is called twice, so to prevent a double conversion
            // we need to explicitly set the timezone.
            $end_date->setTimeZone(timezone_open(drupal_get_user_timezone()));
            $end_date->setTime(23, 59, 59);
            $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
            break;

          default:
            $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
            break;
        }
        // Adjust the date for storage.
        $end_date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
        $item['end_value'] = $end_date->format($format);
      }
    }

    return $values;
  }

  /**
   * #element_validate callback to ensure that the start date <= the end date.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $start_date = $element['value']['#value']['object'];
    $end_date = $element['end_value']['#value']['object'];

    if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
      if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
        $interval = $start_date->diff($end_date);
        if ($interval->invert === 1) {
          $form_state->setError($element, $this->t('The @title end date cannot be before the start date', ['@title' => $element['#title']]));
        }
      }
    }
  }

}
