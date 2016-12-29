<?php

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class ImageCommands extends DrushCommands {

  /**
   * Flush all derived images for a given style.
   *
   * @command image-flush
   * @param $stylename An image style machine name. If not provided, user may choose from a list of names.
   * @option all Flush all derived images
   * @usage drush image-flush
   *   Pick an image style and then delete its images.
   * @usage drush image-flush thumbnail
   *   Delete all thumbnail images.
   * @usage drush image-flush --all
   *   Flush all derived images. They will be regenerated on the fly.
   * @validate-entity-load image_style style
   * @aliases if
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @todo @complete
   */
  public function flush($stylename = NULL, $options = ['all' => FALSE]) {
    if ($options['all']) {
      foreach (\Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple() as $style_name => $style) {
        if ($style = \Drupal::entityTypeManager()->getStorage('image_style')->load($style_name)) {
          $style->flush();
          $this->logger()->success(dt('Image style !style_name flushed', array('!style_name' => $style_name)));
        }
      }
      $this->logger()->success(dt('All image styles flushed'));
    }
    else {
      if (empty($style_name)) {
        $styles = array_keys(\Drupal::entityTypeManager()
          ->getStorage('image_style')
          ->loadMultiple());
        $choices = array_combine($styles, $styles);
        $choices = array_merge(array('all' => 'all'), $choices);
        $style_name = drush_choice($choices, dt("Choose a style to flush."));
        if ($style_name === FALSE) {
          return drush_user_abort();
        }
      }

      if ($style = \Drupal::entityTypeManager()->getStorage('image_style')->load($style_name)) {
        $style->flush();
        $this->logger()->success(dt('Image style !style_name flushed', array('!style_name' => $style_name)));
      }
      elseif ($style_name == 'all') {
        self::flush($style_name, ['all' => TRUE]);
      }
    }
  }

  /**
   * Create an image derivative.
   *
   * @command image-derive
   * @param $style_name An image style machine name.
   * @param $source Path to a source image. Optionally prepend stream wrapper scheme.
   * @usage drush image-derive thumbnail core/themes/bartik/screenshot.png
   *   Save thumbnail sized derivative of logo image.
   * @validate-entity-load image_style style
   * @validate-file-exists source
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases id
   */
  public function derive($style_name, $source)  {
    $image_style = \Drupal::entityTypeManager()->getStorage('image_style')->load($style_name);
    $derivative_uri = $image_style->buildUri($source);
    if ($image_style->createDerivative($source, $derivative_uri)) {
      return $derivative_uri;
    }
  }

}