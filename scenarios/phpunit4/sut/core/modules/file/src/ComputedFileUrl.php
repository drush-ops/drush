<?php

namespace Drupal\file;

use Drupal\Core\TypedData\TypedData;

/**
 * Computed file URL property class.
 */
class ComputedFileUrl extends TypedData {

  /**
   * Computed root-relative file URL.
   *
   * @var string
   */
  protected $url = NULL;

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if ($this->url !== NULL) {
      return $this->url;
    }

    assert($this->getParent()->getEntity() instanceof FileInterface);

    $uri = $this->getParent()->getEntity()->getFileUri();
    $this->url = file_url_transform_relative(file_create_url($uri));

    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE) {
    $this->url = $value;

    // Notify the parent of any changes.
    if ($notify && isset($this->parent)) {
      $this->parent->onChange($this->name);
    }
  }

}
