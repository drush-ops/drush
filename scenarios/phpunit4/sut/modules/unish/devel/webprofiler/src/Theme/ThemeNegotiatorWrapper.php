<?php

namespace Drupal\webprofiler\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiator;

/**
 * Class ThemeNegotiatorWrapper
 */
class ThemeNegotiatorWrapper extends ThemeNegotiator {

  /**
   * @var \Drupal\Core\Theme\ThemeNegotiatorInterface
   */
  private $negotiator;

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    // This method has changed in Drupal 8.4.x, to maintain compatibility with
    // Drupal 8.3.x we check the existence or not of the classResolver
    // property.
    // TODO: remove this logic when we decide to drop Drupal 8.3.x support.
    if (property_exists($this, 'classResolver')) {
      $classResolver = $this->classResolver;
      $negotiators = $this->negotiators;
    } else {
      $classResolver = \Drupal::classResolver();
      $negotiators = $this->getSortedNegotiators();
    }

    foreach ($negotiators as $negotiator_id) {
      if (property_exists($this, 'classResolver')) {
        $negotiator = $classResolver->getInstanceFromDefinition($negotiator_id);
      } else {
        $negotiator = $negotiator_id;
      }

      if ($negotiator->applies($route_match)) {
        $theme = $negotiator->determineActiveTheme($route_match);
        if ($theme !== NULL && $this->themeAccess->checkAccess($theme)) {
          $this->negotiator = $negotiator;
          return $theme;
        }
      }
    }
  }

  /**
   * @return \Drupal\Core\Theme\ThemeNegotiatorInterface
   */
  public function getNegotiator() {
    return $this->negotiator;
  }

  /**
   * Returns the sorted array of theme negotiators.
   *
   * @return array|\Drupal\Core\Theme\ThemeNegotiatorInterface[]
   *   An array of theme negotiator objects.
   *
   * TODO: remove this method when we decide to drop Drupal 8.3.x support.
   */
  protected function getSortedNegotiators() {
    if (!isset($this->sortedNegotiators)) {
      // Sort the negotiators according to priority.
      krsort($this->negotiators);
      // Merge nested negotiators from $this->negotiators into
      // $this->sortedNegotiators.
      $this->sortedNegotiators = [];
      foreach ($this->negotiators as $builders) {
        $this->sortedNegotiators = array_merge($this->sortedNegotiators, $builders);
      }
    }
    return $this->sortedNegotiators;
  }

}
