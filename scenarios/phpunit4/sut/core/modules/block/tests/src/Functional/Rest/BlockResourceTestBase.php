<?php

namespace Drupal\Tests\block\Functional\Rest;

use Drupal\block\Entity\Block;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;

abstract class BlockResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'block';

  /**
   * @var \Drupal\block\BlockInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->entity->setVisibilityConfig('user_role', [])->save();
        break;
      case 'POST':
        $this->grantPermissionsToTestedRole(['administer blocks']);
        break;
      case 'PATCH':
        $this->grantPermissionsToTestedRole(['administer blocks']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $block = Block::create([
      'plugin' => 'llama_block',
      'region' => 'header',
      'id' => 'llama',
      'theme' => 'classy',
    ]);
    // All blocks can be viewed by the anonymous user by default. An interesting
    // side effect of this is that any anonymous user is also able to read the
    // corresponding block config entity via REST, even if an authentication
    // provider is configured for the block config entity REST resource! In
    // other words: Block entities do not distinguish between 'view' as in
    // "render on a page" and 'view' as in "read the configuration".
    // This prevents that.
    // @todo Fix this in https://www.drupal.org/node/2820315.
    $block->setVisibilityConfig('user_role', [
      'id' => 'user_role',
      'roles' => ['non-existing-role' => 'non-existing-role'],
      'negate' => FALSE,
      'context_mapping' => [
        'user' => '@user.current_user_context:current_user',
      ],
    ]);
    $block->save();

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $normalization = [
      'uuid' => $this->entity->uuid(),
      'id' => 'llama',
      'weight' => NULL,
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
        'theme' => [
          'classy',
        ],
      ],
      'theme' => 'classy',
      'region' => 'header',
      'provider' => NULL,
      'plugin' => 'llama_block',
      'settings' => [
        'id' => 'broken',
        'label' => '',
        'provider' => 'core',
        'label_display' => 'visible',
      ],
      'visibility' => [],
    ];

    return $normalization;
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
    // @see ::createEntity()
    return ['url.site'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheTags() {
    // Because the 'user.permissions' cache context is missing, the cache tag
    // for the anonymous user role is never added automatically.
    return array_values(array_diff(parent::getExpectedCacheTags(), ['config:user.role.anonymous']));
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
        return "You are not authorized to view this block entity.";
      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    // @see \Drupal\block\BlockAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedAccessCacheability()
      ->setCacheTags([
        '4xx-response',
        'config:block.block.llama',
        'http_response',
        static::$auth ? 'user:2' : 'user:0',
      ])
      ->setCacheContexts(['user.roles']);
  }

}
