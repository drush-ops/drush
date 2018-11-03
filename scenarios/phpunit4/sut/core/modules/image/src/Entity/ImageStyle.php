<?php

namespace Drupal\image\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Routing\RequestHelper;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\image\ImageEffectPluginCollection;
use Drupal\image\ImageEffectInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Defines an image style configuration entity.
 *
 * @ConfigEntityType(
 *   id = "image_style",
 *   label = @Translation("Image style"),
 *   label_collection = @Translation("Image styles"),
 *   label_singular = @Translation("image style"),
 *   label_plural = @Translation("image styles"),
 *   label_count = @PluralTranslation(
 *     singular = "@count image style",
 *     plural = "@count image styles",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\image\Form\ImageStyleAddForm",
 *       "edit" = "Drupal\image\Form\ImageStyleEditForm",
 *       "delete" = "Drupal\image\Form\ImageStyleDeleteForm",
 *       "flush" = "Drupal\image\Form\ImageStyleFlushForm"
 *     },
 *     "list_builder" = "Drupal\image\ImageStyleListBuilder",
 *     "storage" = "Drupal\image\ImageStyleStorage",
 *   },
 *   admin_permission = "administer image styles",
 *   config_prefix = "style",
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label"
 *   },
 *   links = {
 *     "flush-form" = "/admin/config/media/image-styles/manage/{image_style}/flush",
 *     "edit-form" = "/admin/config/media/image-styles/manage/{image_style}",
 *     "delete-form" = "/admin/config/media/image-styles/manage/{image_style}/delete",
 *     "collection" = "/admin/config/media/image-styles",
 *   },
 *   config_export = {
 *     "name",
 *     "label",
 *     "effects",
 *   }
 * )
 */
class ImageStyle extends ConfigEntityBase implements ImageStyleInterface, EntityWithPluginCollectionInterface {

  /**
   * The name of the image style.
   *
   * @var string
   */
  protected $name;

  /**
   * The image style label.
   *
   * @var string
   */
  protected $label;

  /**
   * The array of image effects for this image style.
   *
   * @var array
   */
  protected $effects = [];

  /**
   * Holds the collection of image effects that are used by this image style.
   *
   * @var \Drupal\image\ImageEffectPluginCollection
   */
  protected $effectsCollection;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if ($update) {
      if (!empty($this->original) && $this->id() !== $this->original->id()) {
        // The old image style name needs flushing after a rename.
        $this->original->flush();
        // Update field settings if necessary.
        if (!$this->isSyncing()) {
          static::replaceImageStyle($this);
        }
      }
      else {
        // Flush image style when updating without changing the name.
        $this->flush();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    /** @var \Drupal\image\ImageStyleInterface[] $entities */
    foreach ($entities as $style) {
      // Flush cached media for the deleted style.
      $style->flush();
      // Clear the replacement ID, if one has been previously stored.
      /** @var \Drupal\image\ImageStyleStorageInterface $storage */
      $storage->clearReplacementId($style->id());
    }
  }

  /**
   * Update field settings if the image style name is changed.
   *
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style.
   */
  protected static function replaceImageStyle(ImageStyleInterface $style) {
    if ($style->id() != $style->getOriginalId()) {
      // Loop through all entity displays looking for formatters / widgets using
      // the image style.
      foreach (EntityViewDisplay::loadMultiple() as $display) {
        foreach ($display->getComponents() as $name => $options) {
          if (isset($options['type']) && $options['type'] == 'image' && $options['settings']['image_style'] == $style->getOriginalId()) {
            $options['settings']['image_style'] = $style->id();
            $display->setComponent($name, $options)
              ->save();
          }
        }
      }
      foreach (EntityViewDisplay::loadMultiple() as $display) {
        foreach ($display->getComponents() as $name => $options) {
          if (isset($options['type']) && $options['type'] == 'image_image' && $options['settings']['preview_image_style'] == $style->getOriginalId()) {
            $options['settings']['preview_image_style'] = $style->id();
            $display->setComponent($name, $options)
              ->save();
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildUri($uri) {
    $source_scheme = $scheme = $this->fileUriScheme($uri);
    $default_scheme = $this->fileDefaultScheme();

    if ($source_scheme) {
      $path = $this->fileUriTarget($uri);
      // The scheme of derivative image files only needs to be computed for
      // source files not stored in the default scheme.
      if ($source_scheme != $default_scheme) {
        $class = $this->getStreamWrapperManager()->getClass($source_scheme);
        $is_writable = $class::getType() & StreamWrapperInterface::WRITE;

        // Compute the derivative URI scheme. Derivatives created from writable
        // source stream wrappers will inherit the scheme. Derivatives created
        // from read-only stream wrappers will fall-back to the default scheme.
        $scheme = $is_writable ? $source_scheme : $default_scheme;
      }
    }
    else {
      $path = $uri;
      $source_scheme = $scheme = $default_scheme;
    }
    return "$scheme://styles/{$this->id()}/$source_scheme/{$this->addExtension($path)}";
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrl($path, $clean_urls = NULL) {
    $uri = $this->buildUri($path);
    // The token query is added even if the
    // 'image.settings:allow_insecure_derivatives' configuration is TRUE, so
    // that the emitted links remain valid if it is changed back to the default
    // FALSE. However, sites which need to prevent the token query from being
    // emitted at all can additionally set the
    // 'image.settings:suppress_itok_output' configuration to TRUE to achieve
    // that (if both are set, the security token will neither be emitted in the
    // image derivative URL nor checked for in
    // \Drupal\image\ImageStyleInterface::deliver()).
    $token_query = [];
    if (!\Drupal::config('image.settings')->get('suppress_itok_output')) {
      // The passed $path variable can be either a relative path or a full URI.
      $original_uri = file_uri_scheme($path) ? file_stream_wrapper_uri_normalize($path) : file_build_uri($path);
      $token_query = [IMAGE_DERIVATIVE_TOKEN => $this->getPathToken($original_uri)];
    }

    if ($clean_urls === NULL) {
      // Assume clean URLs unless the request tells us otherwise.
      $clean_urls = TRUE;
      try {
        $request = \Drupal::request();
        $clean_urls = RequestHelper::isCleanUrl($request);
      }
      catch (ServiceNotFoundException $e) {
      }
    }

    // If not using clean URLs, the image derivative callback is only available
    // with the script path. If the file does not exist, use Url::fromUri() to
    // ensure that it is included. Once the file exists it's fine to fall back
    // to the actual file path, this avoids bootstrapping PHP once the files are
    // built.
    if ($clean_urls === FALSE && file_uri_scheme($uri) == 'public' && !file_exists($uri)) {
      $directory_path = $this->getStreamWrapperManager()->getViaUri($uri)->getDirectoryPath();
      return Url::fromUri('base:' . $directory_path . '/' . file_uri_target($uri), ['absolute' => TRUE, 'query' => $token_query])->toString();
    }

    $file_url = file_create_url($uri);
    // Append the query string with the token, if necessary.
    if ($token_query) {
      $file_url .= (strpos($file_url, '?') !== FALSE ? '&' : '?') . UrlHelper::buildQuery($token_query);
    }

    return $file_url;
  }

  /**
   * {@inheritdoc}
   */
  public function flush($path = NULL) {
    // A specific image path has been provided. Flush only that derivative.
    if (isset($path)) {
      $derivative_uri = $this->buildUri($path);
      if (file_exists($derivative_uri)) {
        file_unmanaged_delete($derivative_uri);
      }
      return $this;
    }

    // Delete the style directory in each registered wrapper.
    $wrappers = $this->getStreamWrapperManager()->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
    foreach ($wrappers as $wrapper => $wrapper_data) {
      if (file_exists($directory = $wrapper . '://styles/' . $this->id())) {
        file_unmanaged_delete_recursive($directory);
      }
    }

    // Let other modules update as necessary on flush.
    $module_handler = \Drupal::moduleHandler();
    $module_handler->invokeAll('image_style_flush', [$this]);

    // Clear caches so that formatters may be added for this style.
    drupal_theme_rebuild();

    Cache::invalidateTags($this->getCacheTagsToInvalidate());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function createDerivative($original_uri, $derivative_uri) {
    // If the source file doesn't exist, return FALSE without creating folders.
    $image = $this->getImageFactory()->get($original_uri);
    if (!$image->isValid()) {
      return FALSE;
    }

    // Get the folder for the final location of this style.
    $directory = drupal_dirname($derivative_uri);

    // Build the destination folder tree if it doesn't already exist.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS)) {
      \Drupal::logger('image')->error('Failed to create style directory: %directory', ['%directory' => $directory]);
      return FALSE;
    }

    foreach ($this->getEffects() as $effect) {
      $effect->applyEffect($image);
    }

    if (!$image->save($derivative_uri)) {
      if (file_exists($derivative_uri)) {
        \Drupal::logger('image')->error('Cached image file %destination already exists. There may be an issue with your rewrite configuration.', ['%destination' => $derivative_uri]);
      }
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    foreach ($this->getEffects() as $effect) {
      $effect->transformDimensions($dimensions, $uri);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeExtension($extension) {
    foreach ($this->getEffects() as $effect) {
      $extension = $effect->getDerivativeExtension($extension);
    }
    return $extension;
  }

  /**
   * {@inheritdoc}
   */
  public function getPathToken($uri) {
    // Return the first 8 characters.
    return substr(Crypt::hmacBase64($this->id() . ':' . $this->addExtension($uri), $this->getPrivateKey() . $this->getHashSalt()), 0, 8);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteImageEffect(ImageEffectInterface $effect) {
    $this->getEffects()->removeInstanceId($effect->getUuid());
    $this->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsUri($uri) {
    // Only support the URI if its extension is supported by the current image
    // toolkit.
    return in_array(
      mb_strtolower(pathinfo($uri, PATHINFO_EXTENSION)),
      $this->getImageFactory()->getSupportedExtensions()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEffect($effect) {
    return $this->getEffects()->get($effect);
  }

  /**
   * {@inheritdoc}
   */
  public function getEffects() {
    if (!$this->effectsCollection) {
      $this->effectsCollection = new ImageEffectPluginCollection($this->getImageEffectPluginManager(), $this->effects);
      $this->effectsCollection->sort();
    }
    return $this->effectsCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['effects' => $this->getEffects()];
  }

  /**
   * {@inheritdoc}
   */
  public function addImageEffect(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getEffects()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getReplacementID() {
    /** @var \Drupal\image\ImageStyleStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage($this->getEntityTypeId());
    return $storage->getReplacementId($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name');
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * Returns the image effect plugin manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The image effect plugin manager.
   */
  protected function getImageEffectPluginManager() {
    return \Drupal::service('plugin.manager.image.effect');
  }

  /**
   * Returns the image factory.
   *
   * @return \Drupal\Core\Image\ImageFactory
   *   The image factory.
   */
  protected function getImageFactory() {
    return \Drupal::service('image.factory');
  }

  /**
   * Gets the Drupal private key.
   *
   * @return string
   *   The Drupal private key.
   */
  protected function getPrivateKey() {
    return \Drupal::service('private_key')->get();
  }

  /**
   * Gets a salt useful for hardening against SQL injection.
   *
   * @return string
   *   A salt based on information in settings.php, not in the database.
   *
   * @throws \RuntimeException
   */
  protected function getHashSalt() {
    return Settings::getHashSalt();
  }

  /**
   * Adds an extension to a path.
   *
   * If this image style changes the extension of the derivative, this method
   * adds the new extension to the given path. This way we avoid filename
   * clashes while still allowing us to find the source image.
   *
   * @param string $path
   *   The path to add the extension to.
   *
   * @return string
   *   The given path if this image style doesn't change its extension, or the
   *   path with the added extension if it does.
   */
  protected function addExtension($path) {
    $original_extension = pathinfo($path, PATHINFO_EXTENSION);
    $extension = $this->getDerivativeExtension($original_extension);
    if ($original_extension !== $extension) {
      $path .= '.' . $extension;
    }
    return $path;
  }

  /**
   * Provides a wrapper for file_uri_scheme() to allow unit testing.
   *
   * Returns the scheme of a URI (e.g. a stream).
   *
   * @param string $uri
   *   A stream, referenced as "scheme://target"  or "data:target".
   *
   * @see file_uri_target()
   *
   * @todo: Remove when https://www.drupal.org/node/2050759 is in.
   *
   * @return string
   *   A string containing the name of the scheme, or FALSE if none. For
   *   example, the URI "public://example.txt" would return "public".
   */
  protected function fileUriScheme($uri) {
    return file_uri_scheme($uri);
  }

  /**
   * Provides a wrapper for file_uri_target() to allow unit testing.
   *
   * Returns the part of a URI after the schema.
   *
   * @param string $uri
   *   A stream, referenced as "scheme://target" or "data:target".
   *
   * @see file_uri_scheme()
   *
   * @todo: Convert file_uri_target() into a proper injectable service.
   *
   * @return string|bool
   *   A string containing the target (path), or FALSE if none.
   *   For example, the URI "public://sample/test.txt" would return
   *   "sample/test.txt".
   */
  protected function fileUriTarget($uri) {
    return file_uri_target($uri);
  }

  /**
   * Provides a wrapper for file_default_scheme() to allow unit testing.
   *
   * Gets the default file stream implementation.
   *
   * @todo: Convert file_default_scheme() into a proper injectable service.
   *
   * @return string
   *   'public', 'private' or any other file scheme defined as the default.
   */
  protected function fileDefaultScheme() {
    return file_default_scheme();
  }

  /**
   * Gets the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   *   The stream wrapper manager service
   *
   * @todo Properly inject this service in Drupal 9.0.x.
   */
  protected function getStreamWrapperManager() {
    return \Drupal::service('stream_wrapper_manager');
  }

}
