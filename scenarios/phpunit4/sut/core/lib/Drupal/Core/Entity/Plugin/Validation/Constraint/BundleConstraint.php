<?php

namespace Drupal\Core\Entity\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a value is a valid entity type.
 *
 * @Constraint(
 *   id = "Bundle",
 *   label = @Translation("Bundle", context = "Validation"),
 *   type = { "entity", "entity_reference" }
 * )
 */
class BundleConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The entity must be of bundle %bundle.';

  /**
   * The bundle option.
   *
   * @var string|array
   */
  public $bundle;

  /**
   * Gets the bundle option as array.
   *
   * @return array
   */
  public function getBundleOption() {
    // Support passing the bundle as string, but force it to be an array.
    if (!is_array($this->bundle)) {
      $this->bundle = [$this->bundle];
    }
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption() {
    return 'bundle';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions() {
    return ['bundle'];
  }

}
