<?php

namespace Drupal\image;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Image\ImageInterface;

/**
 * Defines the interface for image effects.
 *
 * @see \Drupal\image\Annotation\ImageEffect
 * @see \Drupal\image\ImageEffectBase
 * @see \Drupal\image\ConfigurableImageEffectInterface
 * @see \Drupal\image\ConfigurableImageEffectBase
 * @see \Drupal\image\ImageEffectManager
 * @see plugin_api
 */
interface ImageEffectInterface extends PluginInspectionInterface, ConfigurablePluginInterface {

  /**
   * Applies an image effect to the image object.
   *
   * @param \Drupal\Core\Image\ImageInterface $image
   *   An image file object.
   *
   * @return bool
   *   TRUE on success. FALSE if unable to perform the image effect on the image.
   */
  public function applyEffect(ImageInterface $image);

  /**
   * Determines the dimensions of the styled image.
   *
   * @param array &$dimensions
   *   Dimensions to be modified - an array with the following keys:
   *   - width: the width in pixels, or NULL if unknown
   *   - height: the height in pixels, or NULL if unknown
   *   When either of the dimensions are NULL, the corresponding HTML attribute
   *   will be omitted when an image style using this image effect is used.
   * @param string $uri
   *   Original image file URI. It is passed in to allow an effect to
   *   optionally use this information to retrieve additional image metadata
   *   to determine dimensions of the styled image.
   *   ImageEffectInterface::transformDimensions key objective is to calculate
   *   styled image dimensions without performing actual image operations, so
   *   be aware that performing IO on the URI may lead to decrease in
   *   performance.
   */
  public function transformDimensions(array &$dimensions, $uri);

  /**
   * Returns the extension of the derivative after applying this image effect.
   *
   * @param string $extension
   *   The file extension the derivative has before applying.
   *
   * @return string
   *   The file extension after applying.
   */
  public function getDerivativeExtension($extension);

  /**
   * Returns a render array summarizing the configuration of the image effect.
   *
   * @return array
   *   A render array.
   */
  public function getSummary();

  /**
   * Returns the image effect label.
   *
   * @return string
   *   The image effect label.
   */
  public function label();

  /**
   * Returns the unique ID representing the image effect.
   *
   * @return string
   *   The image effect ID.
   */
  public function getUuid();

  /**
   * Returns the weight of the image effect.
   *
   * @return int|string
   *   Either the integer weight of the image effect, or an empty string.
   */
  public function getWeight();

  /**
   * Sets the weight for this image effect.
   *
   * @param int $weight
   *   The weight for this image effect.
   *
   * @return $this
   */
  public function setWeight($weight);

}
