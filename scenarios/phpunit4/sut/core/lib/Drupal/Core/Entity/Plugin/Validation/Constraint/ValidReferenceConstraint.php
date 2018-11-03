<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 *
 * @Constraint(
 *   id = "ValidReference",
 *   label = @Translation("Entity Reference valid reference", context = "Validation")
 * )
 */
class ValidReferenceConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'This entity (%type: %id) cannot be referenced.';

  /**
   * Violation message when the entity does not exist.
   *
   * @var string
   */
  public $nonExistingMessage = 'The referenced entity (%type: %id) does not exist.';

  /**
   * Violation message when a new entity ("autocreate") is invalid.
   *
   * @var string
   */
  public $invalidAutocreateMessage = 'This entity (%type: %label) cannot be referenced.';

  /**
   * Violation message when the target_id is empty.
   *
   * @var string
   */
  public $nullMessage = 'This value should not be null.';

}
