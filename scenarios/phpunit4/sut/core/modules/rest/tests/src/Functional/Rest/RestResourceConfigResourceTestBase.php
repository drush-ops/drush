<?php

namespace Drupal\Tests\rest\Functional\Rest;

use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\rest\Entity\RestResourceConfig;

abstract class RestResourceConfigResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['dblog'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'rest_resource_config';

  /**
   * @var \Drupal\rest\RestResourceConfigInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer rest resources']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $rest_resource_config = RestResourceConfig::create([
      'id' => 'llama',
      'plugin_id' => 'dblog',
      'granularity' => 'method',
      'configuration' => [
        'GET' => [
          'supported_formats' => [
            'json',
          ],
          'supported_auth' => [
            'cookie',
          ],
        ],
      ],
    ]);
    $rest_resource_config->save();

    return $rest_resource_config;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return [
      'uuid' => $this->entity->uuid(),
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
        'module' => [
          'dblog',
          'serialization',
          'user',
        ],
      ],
      'id' => 'llama',
      'plugin_id' => 'dblog',
      'granularity' => 'method',
      'configuration' => [
        'GET' => [
          'supported_formats' => [
            'json',
          ],
          'supported_auth' => [
            'cookie',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    // @todo Update in https://www.drupal.org/node/2300677.
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return [
      'user.permissions',
    ];
  }

}
