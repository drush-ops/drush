<?php

namespace Drupal\webprofiler\Config;

use Drupal\Core\Config\ConfigFactory;
use Drupal\webprofiler\DataCollector\ConfigDataCollector;

/**
 * Wraps a config factory to be able to figure out all used config files.
 */
class ConfigFactoryWrapper extends ConfigFactory {

  /**
   * @var \Drupal\webprofiler\DataCollector\ConfigDataCollector
   */
  private $dataCollector;

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    $result = parent::get($name);
    $this->dataCollector->addConfigName($name);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $names) {
    $result = parent::loadMultiple($names);
    foreach (array_keys($result) as $name) {
      $this->dataCollector->addConfigName($name);
    }
    return $result;
  }

  /**
   * @param \Drupal\webprofiler\DataCollector\ConfigDataCollector $dataCollector
   */
  public function setDataCollector(ConfigDataCollector $dataCollector) {
    $this->dataCollector = $dataCollector;
  }
}
