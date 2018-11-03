<?php

namespace Drupal\Core\Template;

use Drupal\Component\Utility\Html;

/**
 * A class that defines a type of boolean HTML attribute.
 *
 * Boolean HTML attributes are not attributes with values of TRUE/FALSE.
 * They are attributes that if they exist in the tag, they are TRUE.
 * Examples include selected, disabled, checked, readonly.
 *
 * To set a boolean attribute on the Attribute class, set it to TRUE.
 * @code
 *  $attributes = new Attribute();
 *  $attributes['disabled'] = TRUE;
 *  echo '<select' . $attributes . '/>';
 *  // produces <select disabled>;
 *  $attributes['disabled'] = FALSE;
 *  echo '<select' . $attributes . '/>';
 *  // produces <select>;
 * @endcode
 *
 * @see \Drupal\Core\Template\Attribute
 */
class AttributeBoolean extends AttributeValueBase {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return $this->__toString();
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString() {
    return $this->value === FALSE ? '' : Html::escape($this->name);
  }

}
