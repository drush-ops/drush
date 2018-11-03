<?php

namespace Drupal\Tests\media\Functional\Rest;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\Tests\rest\Functional\BcTimestampNormalizerUnixTestTrait;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use GuzzleHttp\RequestOptions;

abstract class MediaResourceTestBase extends EntityResourceTestBase {

  use BcTimestampNormalizerUnixTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['media'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'media';

  /**
   * @var \Drupal\media\MediaInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
        $this->grantPermissionsToTestedRole(['view media']);
        break;

      case 'POST':
        $this->grantPermissionsToTestedRole(['create camelids media', 'access content']);
        break;

      case 'PATCH':
        $this->grantPermissionsToTestedRole(['edit any camelids media']);
        // @todo Remove this in https://www.drupal.org/node/2824851.
        $this->grantPermissionsToTestedRole(['access content']);
        break;

      case 'DELETE':
        $this->grantPermissionsToTestedRole(['delete any camelids media']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    if (!MediaType::load('camelids')) {
      // Create a "Camelids" media type.
      $media_type = MediaType::create([
        'name' => 'Camelids',
        'id' => 'camelids',
        'description' => 'Camelids are large, strictly herbivorous animals with slender necks and long legs.',
        'source' => 'file',
      ]);
      $media_type->save();
      // Create the source field.
      $source_field = $media_type->getSource()->createSourceField($media_type);
      $source_field->getFieldStorageDefinition()->save();
      $source_field->save();
      $media_type
        ->set('source_configuration', [
          'source_field' => $source_field->getName(),
        ])
        ->save();
    }

    // Create a file to upload.
    $file = File::create([
      'uri' => 'public://llama.txt',
    ]);
    $file->setPermanent();
    $file->save();

    // Create a "Llama" media item.
    $media = Media::create([
      'bundle' => 'camelids',
      'field_media_file' => [
        'target_id' => $file->id(),
      ],
    ]);
    $media
      ->setName('Llama')
      ->setPublished()
      ->setCreatedTime(123456789)
      ->setOwnerId(static::$auth ? $this->account->id() : 0)
      ->setRevisionUserId(static::$auth ? $this->account->id() : 0)
      ->save();

    return $media;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $file = File::load(1);
    $thumbnail = File::load(2);
    $author = User::load($this->entity->getOwnerId());
    return [
      'mid' => [
        [
          'value' => 1,
        ],
      ],
      'uuid' => [
        [
          'value' => $this->entity->uuid(),
        ],
      ],
      'vid' => [
        [
          'value' => 1,
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'bundle' => [
        [
          'target_id' => 'camelids',
          'target_type' => 'media_type',
          'target_uuid' => MediaType::load('camelids')->uuid(),
        ],
      ],
      'name' => [
        [
          'value' => 'Llama',
        ],
      ],
      'field_media_file' => [
        [
          'description' => NULL,
          'display' => NULL,
          'target_id' => (int) $file->id(),
          'target_type' => 'file',
          'target_uuid' => $file->uuid(),
          'url' => $file->url(),
        ],
      ],
      'thumbnail' => [
        [
          'alt' => 'Thumbnail',
          'width' => 180,
          'height' => 180,
          'target_id' => (int) $thumbnail->id(),
          'target_type' => 'file',
          'target_uuid' => $thumbnail->uuid(),
          'title' => 'Llama',
          'url' => $thumbnail->url(),
        ],
      ],
      'status' => [
        [
          'value' => TRUE,
        ],
      ],
      'created' => [
        $this->formatExpectedTimestampItemValues(123456789),
      ],
      'changed' => [
        $this->formatExpectedTimestampItemValues($this->entity->getChangedTime()),
      ],
      'revision_created' => [
        $this->formatExpectedTimestampItemValues((int) $this->entity->getRevisionCreationTime()),
      ],
      'default_langcode' => [
        [
          'value' => TRUE,
        ],
      ],
      'uid' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => base_path() . 'user/' . $author->id(),
        ],
      ],
      'revision_user' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => base_path() . 'user/' . $author->id(),
        ],
      ],
      'revision_log_message' => [],
      'revision_translation_affected' => [
        [
          'value' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'bundle' => [
        [
          'target_id' => 'camelids',
        ],
      ],
      'name' => [
        [
          'value' => 'Dramallama',
        ],
      ],
      'field_media_file' => [
        [
          'description' => NULL,
          'display' => NULL,
          'target_id' => 3,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPatchEntity() {
    return array_diff_key($this->getNormalizedPostEntity(), ['field_media_file' => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    if ($this->config('rest.settings')->get('bc_entity_resource_permissions')) {
      return parent::getExpectedUnauthorizedAccessMessage($method);
    }

    switch ($method) {
      case 'GET';
        return "The 'view media' permission is required and the media item must be published.";

      case 'POST':
        return "The following permissions are required: 'administer media' OR 'create media' OR 'create camelids media'.";

      case 'PATCH':
        return "The following permissions are required: 'update any media' OR 'update own media' OR 'camelids: edit any media' OR 'camelids: edit own media'.";

      case 'DELETE':
        return "The following permissions are required: 'delete any media' OR 'delete own media' OR 'camelids: delete any media' OR 'camelids: delete own media'.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function testPost() {
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');

    // Step 1: upload file, results in File entity marked temporary.
    $this->uploadFile();
    $file = $file_storage->loadUnchanged(3);
    $this->assertTrue($file->isTemporary());
    $this->assertFalse($file->isPermanent());

    // Step 2: create Media entity using the File, makes File entity permanent.
    parent::testPost();
    $file = $file_storage->loadUnchanged(3);
    $this->assertFalse($file->isTemporary());
    $this->assertTrue($file->isPermanent());
  }

  /**
   * This duplicates some of the 'file_upload' REST resource plugin test
   * coverage, to be able to test it on a concrete use case.
   */
  protected function uploadFile() {
    // Enable the 'file_upload' REST resource for the current format + auth.
    $this->resourceConfigStorage->create([
      'id' => 'file.upload',
      'granularity' => RestResourceConfigInterface::RESOURCE_GRANULARITY,
      'configuration' => [
        'methods' => ['POST'],
        'formats' => [static::$format],
        'authentication' => isset(static::$auth) ? [static::$auth] : [],
      ],
      'status' => TRUE,
    ])->save();
    $this->refreshTestStateAfterRestConfigChange();

    $this->initAuthentication();

    // POST to create a File entity.
    $url = Url::fromUri('base:file/upload/media/camelids/field_media_file');
    $url->setOption('query', ['_format' => static::$format]);
    $request_options = [];
    $request_options[RequestOptions::HEADERS] = [
      // Set the required (and only accepted) content type for the request.
      'Content-Type' => 'application/octet-stream',
      // Set the required Content-Disposition header for the file name.
      'Content-Disposition' => 'file; filename="drupal rocks 🤘.txt"',
    ];
    $request_options[RequestOptions::BODY] = 'Drupal is the best!';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions('POST'));
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, $this->getExpectedUnauthorizedAccessMessage('POST'), $response);

    // Grant necessary permission, retry.
    $this->grantPermissionsToTestedRole(['create camelids media']);
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(201, $response->getStatusCode());
    $expected = $this->getExpectedNormalizedFileEntity();
    static::recursiveKSort($expected);
    $actual = $this->serializer->decode((string) $response->getBody(), static::$format);
    static::recursiveKSort($actual);
    $this->assertSame($expected, $actual);

    // To still run the complete test coverage for POSTing a Media entity, we
    // must revoke the additional permissions that we granted.
    $role = Role::load(static::$auth ? RoleInterface::AUTHENTICATED_ID : RoleInterface::AUTHENTICATED_ID);
    $role->revokePermission('create camelids media');
    $role->trustData()->save();
  }

  /**
   * Gets the expected file entity.
   *
   * @return array
   *   The expected normalized data array.
   */
  protected function getExpectedNormalizedFileEntity() {
    $file = File::load(3);
    $owner = static::$auth ? $this->account : User::load(0);

    return [
      'fid' => [
        [
          'value' => 3,
        ],
      ],
      'uuid' => [
        [
          'value' => $file->uuid(),
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'uid' => [
        [
          'target_id' => (int) $owner->id(),
          'target_type' => 'user',
          'target_uuid' => $owner->uuid(),
          'url' => base_path() . 'user/' . $owner->id(),
        ],
      ],
      'filename' => [
        [
          'value' => 'drupal rocks 🤘.txt',
        ],
      ],
      'uri' => [
        [
          'value' => 'public://' . date('Y-m') . '/drupal rocks 🤘.txt',
          'url' => base_path() . $this->siteDirectory . '/files/' . date('Y-m') . '/drupal%20rocks%20%F0%9F%A4%98.txt',
        ],
      ],
      'filemime' => [
        [
          'value' => 'text/plain',
        ],
      ],
      'filesize' => [
        [
          'value' => 19,
        ],
      ],
      'status' => [
        [
          'value' => FALSE,
        ],
      ],
      'created' => [
        $this->formatExpectedTimestampItemValues($file->getCreatedTime()),
      ],
      'changed' => [
        $this->formatExpectedTimestampItemValues($file->getChangedTime()),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessCacheability() {
    // @see \Drupal\media\MediaAccessControlHandler::checkAccess()
    return parent::getExpectedUnauthorizedAccessCacheability()
      ->addCacheTags(['media:1']);
  }

}
