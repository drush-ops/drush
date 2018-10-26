<?php

namespace Drupal\devel;

use Drupal\Core\Plugin\PluginBase;
use Drupal\devel\Render\FilteredMarkup;

/**
 * Defines a base devel dumper implementation.
 *
 * @see \Drupal\devel\Annotation\DevelDumper
 * @see \Drupal\devel\DevelDumperInterface
 * @see \Drupal\devel\DevelDumperPluginManager
 * @see plugin_api
 */
abstract class DevelDumperBase extends PluginBase implements DevelDumperInterface {

  /**
   * {@inheritdoc}
   */
  public function dump($input, $name = NULL) {
    echo (string) $this->export($input, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function exportAsRenderable($input, $name = NULL) {
    return ['#markup' => $this->export($input, $name)];
  }

  /**
   * Wrapper for \Drupal\Core\Render\Markup::create().
   *
   * @param string $input
   *   The input string to mark as safe.
   *
   * @return string
   *   The unaltered input value.
   */
  protected function setSafeMarkup($input) {
    return FilteredMarkup::create($input);
  }

}
