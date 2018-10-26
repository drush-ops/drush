<?php

namespace Drupal\webprofiler\Profiler;

use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;

/**
 * Class ProfilerStorageManager
 */
class ProfilerStorageManager {

  /**
   * @var array
   */
  private $storages;

  /**
   * @return array
   */
  public function getStorages() {
    $output = [];

    /** @var \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface $storage */
    foreach ($this->storages as $id => $storage) {
      $output[$id] = $storage['title'];
    }

    return $output;
  }

  /**
   * @param $id
   *
   * @return array
   */
  public function getStorage($id) {
    return $this->storages[$id];
  }

  /**
   * @param $id
   * @param $title
   * @param \Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface $storage
   */
  public function addStorage($id, $title, ProfilerStorageInterface $storage) {
    $this->storages[$id] = [
      'title' => $title,
      'class' => $storage,
    ];
  }

}
