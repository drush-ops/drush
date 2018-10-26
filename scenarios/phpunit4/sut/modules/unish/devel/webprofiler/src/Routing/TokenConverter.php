<?php

namespace Drupal\webprofiler\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Class TokenConverter
 */
class TokenConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    // "profiler" service isn't injected to prevent circular reference when
    // more than one language is active and "Account administration pages" is
    // enabled on admin/config/regional/language/detection. See #2710787 for
    // more information.
    /** @var \Drupal\webprofiler\Profiler\Profiler $profiler */
    $profiler = \Drupal::service('profiler');

    if (NULL === $profiler) {
      return NULL;
    }

    $profile = $profiler->loadProfile($value);

    if (NULL === $profile) {
      return NULL;
    }

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (!empty($definition['type']) && $definition['type'] === 'webprofiler:token') {
      return TRUE;
    }
    return FALSE;
  }
}
