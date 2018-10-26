<?php

namespace Drupal\webprofiler\Profiler;

use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage as SymfonyFileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

class FileProfilerStorage extends SymfonyFileProfilerStorage {

  /**
   * {@inheritdoc}
   */
  protected function createProfileFromData($token, $data, $parent = NULL) {
    $profile = new Profile($token);
    $profile->setIp($data['ip']);
    $profile->setMethod($data['method']);
    $profile->setUrl($data['url']);
    $profile->setTime($data['time']);
    $profile->setCollectors($data['data']);
    $profile->setStatusCode($data['status_code']);

    return $profile;
  }
}
