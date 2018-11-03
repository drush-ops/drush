<?php

namespace Drupal\Tests\entity_test\Functional\Rest;

use Drupal\entity_test\Entity\EntityTestLabel;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\user\Entity\User;

abstract class EntityTestLabelResourceTestBase extends EntityResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_test'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'entity_test_label';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [];

  /**
   * @var \Drupal\entity_test\Entity\EntityTestLabel
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['view test entity']);
        break;
      case 'POST':
        $this->grantPermissionsToTestedRole([
          'administer entity_test content',
          'administer entity_test_with_bundle content',
          'create entity_test entity_test_with_bundle entities',
        ]);
        break;
      case 'PATCH':
      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer entity_test content']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $entity_test_label = EntityTestLabel::create([
      'name' => 'label_llama',
    ]);
    $entity_test_label->setOwnerId(0);
    $entity_test_label->save();
    return $entity_test_label;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $author = User::load(0);
    $normalization = [
      'uuid' => [
        [
          'value' => $this->entity->uuid(),
        ],
      ],
      'id' => [
        [
          'value' => (int) $this->entity->id(),
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'type' => [
        [
          'value' => 'entity_test_label',
        ],
      ],
      'name' => [
        [
          'value' => 'label_llama',
        ],
      ],
      'created' => [
        $this->formatExpectedTimestampItemValues((int) $this->entity->get('created')->value),
      ],
      'user_id' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => $author->toUrl()->toString(),
        ],
      ],
    ];

    return $normalization;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'type' => [
        [
          'value' => 'entity_test_label',
        ],
      ],
      'name' => [
        [
          'value' => 'label_llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    if ($this->config('rest.settings')->get('bc_entity_resource_permissions')) {
      return parent::getExpectedUnauthorizedAccessMessage($method);
    }

    switch ($method) {
      case 'GET':
        return "The 'view test entity' permission is required.";
      case 'POST':
        return "The following permissions are required: 'administer entity_test content' OR 'administer entity_test_with_bundle content' OR 'create entity_test_label entity_test_with_bundle entities'.";
      case 'PATCH':
      case 'DELETE':
        return "The 'administer entity_test content' permission is required.";
      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

}
