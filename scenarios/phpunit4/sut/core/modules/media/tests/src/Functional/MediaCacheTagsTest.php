<?php

namespace Drupal\Tests\media\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\system\Functional\Entity\EntityWithUriCacheTagsTestBase;

/**
 * Tests the media items cache tags.
 *
 * @group media
 */
class MediaCacheTagsTest extends EntityWithUriCacheTagsTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'media',
    'media_test_source',
  ];

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a media type.
    $mediaType = $this->createMediaType('test');

    // Create a media item.
    $media = Media::create([
      'bundle' => $mediaType->id(),
      'name' => 'Unnamed',
    ]);
    $media->save();

    return $media;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalCacheContextsForEntity(EntityInterface $media) {
    return ['timezone'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalCacheTagsForEntity(EntityInterface $media) {
    // Each media item must have an author and a thumbnail.
    return [
      'user:' . $media->getOwnerId(),
      'config:image.style.thumbnail',
      'file:' . $media->get('thumbnail')->entity->id(),
    ];
  }

}
