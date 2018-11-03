<?php

namespace Drupal\user\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if the user's email address is provided if required.
 *
 * The user mail field is NOT required if account originally had no mail set
 * and the user performing the edit has 'administer users' permission.
 * This allows users without email address to be edited and deleted.
 *
 * @Constraint(
 *   id = "UserMailRequired",
 *   label = @Translation("User email required", context = "Validation")
 * )
 */
class UserMailRequired extends Constraint {

  /**
   * Violation message. Use the same message as FormValidator.
   *
   * Note that the name argument is not sanitized so that translators only have
   * one string to translate. The name is sanitized in self::validate().
   *
   * @var string
   */
  public $message = '@name field is required.';

}
