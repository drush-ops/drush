<?php

namespace Drupal\link\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines an access validation constraint for links.
 *
 * @Constraint(
 *   id = "LinkAccess",
 *   label = @Translation("Link URI can be accessed by the user.", context = "Validation"),
 * )
 */
class LinkAccessConstraint extends Constraint {

  public $message = "The path '@uri' is inaccessible.";

}
