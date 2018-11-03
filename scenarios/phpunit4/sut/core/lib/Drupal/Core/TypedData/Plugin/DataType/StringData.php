<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\TypedData\PrimitiveBase;
use Drupal\Core\TypedData\Type\StringInterface;

/**
 * The string data type.
 *
 * The plain value of a string is a regular PHP string. For setting the value
 * any PHP variable that casts to a string may be passed.
 *
 * @DataType(
 *   id = "string",
 *   label = @Translation("String")
 * )
 */
class StringData extends PrimitiveBase implements StringInterface {

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    return $this->getString();
  }

}
