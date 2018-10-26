<?php

namespace Drupal\webprofiler;

/**
 * Generic class Decorator.
 */
class Decorator {

  /**
   * @var
   */
  protected $object;

  /**
   * Class constructor.
   *
   * @param object $object
   *   The object to decorate.
   */
  public function __construct($object) {
    $this->object = $object;
  }

  /**
   * Return the original (i.e. non decorated) object.
   *
   * @return mixed
   *   The original object.
   */
  public function getOriginalObject() {
    $object = $this->object;
    while ($object instanceof Decorator) {
      $object = $object->getOriginalObject();
    }
    return $object;
  }

  /**
   * Returns true if $method is a PHP callable.
   *
   * @param string $method
   *   The method name.
   * @param bool $checkSelf
   *
   * @return bool|mixed
   */
  public function isCallable($method, $checkSelf = FALSE) {
    //Check the original object
    $object = $this->getOriginalObject();
    if (is_callable([$object, $method])) {
      return $object;
    }
    // Check Decorators.
    $object = $checkSelf ? $this : $this->object;
    while ($object instanceof Decorator) {
      if (is_callable([$object, $method])) {
        return $object;
      }
      $object = $this->object;
    }
    return FALSE;
  }

  /**
   * @param $method
   * @param $args
   *
   * @return mixed
   *
   * @throws \Exception
   */
  public function __call($method, $args) {
    if ($object = $this->isCallable($method)) {
      return call_user_func_array([$object, $method], $args);
    }
    throw new \Exception(
      'Undefined method - ' . get_class($this->getOriginalObject()) . '::' . $method
    );
  }

  /**
   * @param $property
   *
   * @return null
   */
  public function __get($property) {
    $object = $this->getOriginalObject();
    if (property_exists($object, $property)) {
      return $object->$property;
    }
    return NULL;
  }
}
