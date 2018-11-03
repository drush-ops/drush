<?php

namespace Drupal\Tests;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Provides a class for checking configuration schema.
 */
trait SchemaCheckTestTrait {

  use SchemaCheckTrait;

  /**
   * Asserts the TypedConfigManager has a valid schema for the configuration.
   *
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The TypedConfigManager.
   * @param string $config_name
   *   The configuration name.
   * @param array $config_data
   *   The configuration data.
   */
  public function assertConfigSchema(TypedConfigManagerInterface $typed_config, $config_name, $config_data) {
    $errors = $this->checkConfigSchema($typed_config, $config_name, $config_data);
    if ($errors === FALSE) {
      // @todo Since the use of this trait is under TestBase, it works.
      //   Can be fixed as part of https://www.drupal.org/node/2260053.
      $this->fail(new FormattableMarkup('No schema for @config_name', ['@config_name' => $config_name]));
      return;
    }
    elseif ($errors === TRUE) {
      // @todo Since the use of this trait is under TestBase, it works.
      //   Can be fixed as part of https://www.drupal.org/node/2260053.
      $this->pass(new FormattableMarkup('Schema found for @config_name and values comply with schema.', ['@config_name' => $config_name]));
    }
    else {
      foreach ($errors as $key => $error) {
        // @todo Since the use of this trait is under TestBase, it works.
        //   Can be fixed as part of https://www.drupal.org/node/2260053.
        $this->fail(new FormattableMarkup('Schema key @key failed with: @error', ['@key' => $key, '@error' => $error]));
      }
    }
  }

  /**
   * Asserts configuration, specified by name, has a valid schema.
   *
   * @param string $config_name
   *   The configuration name.
   */
  public function assertConfigSchemaByName($config_name) {
    $config = $this->config($config_name);
    $this->assertConfigSchema(\Drupal::service('config.typed'), $config->getName(), $config->get());
  }

}
