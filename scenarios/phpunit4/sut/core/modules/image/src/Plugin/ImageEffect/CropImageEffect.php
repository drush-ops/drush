<?php

namespace Drupal\image\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;

/**
 * Crops an image resource.
 *
 * @ImageEffect(
 *   id = "image_crop",
 *   label = @Translation("Crop"),
 *   description = @Translation("Resizing will make images an exact set of dimensions. This may cause images to be stretched or shrunk disproportionately.")
 * )
 */
class CropImageEffect extends ResizeImageEffect {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    list($x, $y) = explode('-', $this->configuration['anchor']);
    $x = image_filter_keyword($x, $image->getWidth(), $this->configuration['width']);
    $y = image_filter_keyword($y, $image->getHeight(), $this->configuration['height']);
    if (!$image->crop($x, $y, $this->configuration['width'], $this->configuration['height'])) {
      $this->logger->error('Image crop failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', ['%toolkit' => $image->getToolkitId(), '%path' => $image->getSource(), '%mimetype' => $image->getMimeType(), '%dimensions' => $image->getWidth() . 'x' . $image->getHeight()]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = [
      '#theme' => 'image_crop_summary',
      '#data' => $this->configuration,
    ];
    $summary += parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'anchor' => 'center-center',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['anchor'] = [
      '#type' => 'radios',
      '#title' => t('Anchor'),
      '#options' => [
        'left-top' => t('Top left'),
        'center-top' => t('Top center'),
        'right-top' => t('Top right'),
        'left-center' => t('Center left'),
        'center-center' => t('Center'),
        'right-center' => t('Center right'),
        'left-bottom' => t('Bottom left'),
        'center-bottom' => t('Bottom center'),
        'right-bottom' => t('Bottom right'),
      ],
      '#theme' => 'image_anchor',
      '#default_value' => $this->configuration['anchor'],
      '#description' => t('The part of the image that will be retained during the crop.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['anchor'] = $form_state->getValue('anchor');
  }

}
