<?php

namespace Drupal\Core\Field\Plugin\Field\FieldType;

/**
 * Defines the 'created' entity field type.
 *
 * @FieldType(
 *   id = "created",
 *   label = @Translation("Created"),
 *   description = @Translation("An entity field containing a UNIX timestamp of when the entity has been created."),
 *   no_ui = TRUE,
 *   default_widget = "datetime_timestamp",
 *   default_formatter = "timestamp"
 * )
 */
class CreatedItem extends TimestampItem {

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    parent::applyDefaultValue($notify);
    // Created fields default to the current timestamp.
    $this->setValue(['value' => REQUEST_TIME], $notify);
    return $this;
  }

}
