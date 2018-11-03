<?php

namespace Drupal\image;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an image style entity.
 */
interface ImageStyleInterface extends ConfigEntityInterface {

  /**
   * Returns the replacement ID.
   *
   * @return string|null
   *   The replacement image style ID or NULL if no replacement has been
   *   selected.
   *
   * @deprecated in Drupal 8.0.x, will be removed before Drupal 9.0.x. Use
   *   \Drupal\image\ImageStyleStorageInterface::getReplacementId() instead.
   *
   * @see \Drupal\image\ImageStyleStorageInterface::getReplacementId()
   */
  public function getReplacementID();

  /**
   * Returns the image style.
   *
   * @return string
   *   The name of the image style.
   */
  public function getName();

  /**
   * Sets the name of the image style.
   *
   * @param string $name
   *   The name of the image style.
   *
   * @return \Drupal\image\ImageStyleInterface
   *   The class instance this method is called on.
   */
  public function setName($name);

  /**
   * Returns the URI of this image when using this style.
   *
   * The path returned by this function may not exist. The default generation
   * method only creates images when they are requested by a user's browser.
   * Modules may implement this method to decide where to place derivatives.
   *
   * @param string $uri
   *   The URI or path to the original image.
   *
   * @return string
   *   The URI to the image derivative for this style.
   */
  public function buildUri($uri);

  /**
   * Returns the URL of this image derivative for an original image path or URI.
   *
   * @param string $path
   *   The path or URI to the original image.
   * @param mixed $clean_urls
   *   (optional) Whether clean URLs are in use.
   *
   * @return string
   *   The absolute URL where a style image can be downloaded, suitable for use
   *   in an <img> tag. Requesting the URL will cause the image to be created.
   *
   * @see \Drupal\image\Controller\ImageStyleDownloadController::deliver()
   * @see file_url_transform_relative()
   */
  public function buildUrl($path, $clean_urls = NULL);

  /**
   * Generates a token to protect an image style derivative.
   *
   * This prevents unauthorized generation of an image style derivative,
   * which can be costly both in CPU time and disk space.
   *
   * @param string $uri
   *   The URI of the original image of this style.
   *
   * @return string
   *   An eight-character token which can be used to protect image style
   *   derivatives against denial-of-service attacks.
   */
  public function getPathToken($uri);

  /**
   * Flushes cached media for this style.
   *
   * @param string $path
   *   (optional) The original image path or URI. If it's supplied, only this
   *   image derivative will be flushed.
   *
   * @return $this
   */
  public function flush($path = NULL);

  /**
   * Creates a new image derivative based on this image style.
   *
   * Generates an image derivative applying all image effects and saving the
   * resulting image.
   *
   * @param string $original_uri
   *   Original image file URI.
   * @param string $derivative_uri
   *   Derivative image file URI.
   *
   * @return bool
   *   TRUE if an image derivative was generated, or FALSE if the image
   *   derivative could not be generated.
   */
  public function createDerivative($original_uri, $derivative_uri);

  /**
   * Determines the dimensions of this image style.
   *
   * Stores the dimensions of this image style into $dimensions associative
   * array. Implementations have to provide at least values to next keys:
   * - width: Integer with the derivative image width.
   * - height: Integer with the derivative image height.
   *
   * @param array $dimensions
   *   Associative array passed by reference. Implementations have to store the
   *   resulting width and height, in pixels.
   * @param string $uri
   *   Original image file URI. It is passed in to allow effects to
   *   optionally use this information to retrieve additional image metadata
   *   to determine dimensions of the styled image.
   *   ImageStyleInterface::transformDimensions key objective is to calculate
   *   styled image dimensions without performing actual image operations, so
   *   be aware that performing IO on the URI may lead to decrease in
   *   performance.
   *
   * @see ImageEffectInterface::transformDimensions
   */
  public function transformDimensions(array &$dimensions, $uri);

  /**
   * Determines the extension of the derivative without generating it.
   *
   * @param string $extension
   *   The file extension of the original image.
   *
   * @return string
   *   The extension the derivative image will have, given the extension of the
   *   original.
   */
  public function getDerivativeExtension($extension);

  /**
   * Returns a specific image effect.
   *
   * @param string $effect
   *   The image effect ID.
   *
   * @return \Drupal\image\ImageEffectInterface
   *   The image effect object.
   */
  public function getEffect($effect);

  /**
   * Returns the image effects for this style.
   *
   * @return \Drupal\image\ImageEffectPluginCollection|\Drupal\image\ImageEffectInterface[]
   *   The image effect plugin collection.
   */
  public function getEffects();

  /**
   * Saves an image effect for this style.
   *
   * @param array $configuration
   *   An array of image effect configuration.
   *
   * @return string
   *   The image effect ID.
   */
  public function addImageEffect(array $configuration);

  /**
   * Deletes an image effect from this style.
   *
   * @param \Drupal\image\ImageEffectInterface $effect
   *   The image effect object.
   *
   * @return $this
   */
  public function deleteImageEffect(ImageEffectInterface $effect);

  /**
   * Determines if this style can be applied to a given image.
   *
   * @param string $uri
   *   The URI of the image.
   *
   * @return bool
   *   TRUE if the image is supported, FALSE otherwise.
   */
  public function supportsUri($uri);

}
