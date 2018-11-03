<?php

/**
 * @file
 * Contains database additions to drupal-8.bare.standard.php.gz for testing the
 * upgrade path of https://www.drupal.org/node/2455125.
 */

use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;

$connection = Database::getConnection();

// Structure of a view with timestamp fields.
$views_configs = [];

$views_configs[] = Yaml::decode(file_get_contents(__DIR__ . '/drupal-8.views-entity-views-data-2846614.yml'));

foreach ($views_configs as $views_config) {
  $connection->insert('config')
    ->fields([
      'collection',
      'name',
      'data',
    ])
    ->values([
      'collection' => '',
      'name' => 'views.view.' . $views_config['id'],
      'data' => serialize($views_config),
    ])
    ->execute();
}
