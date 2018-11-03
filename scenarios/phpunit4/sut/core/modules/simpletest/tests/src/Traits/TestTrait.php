<?php

namespace Drupal\Tests\simpletest\Traits;

/**
 * A nothing trait, but declared in the Drupal\Tests namespace.
 *
 * We use this trait to test autoloading of traits outside of the normal test
 * suite namespaces.
 *
 * @see \Drupal\Tests\simpletest\Unit\TraitAccessTest
 */
trait TestTrait {

  /**
   * Random string for a not very interesting trait.
   *
   * @var string
   */
  protected $stuff = 'stuff';

  /**
   * Return a test string to a trait user.
   *
   * @return string
   *   Just a random sort of string.
   */
  protected function getStuff() {
    return $this->stuff;
  }

}
