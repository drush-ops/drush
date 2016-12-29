<?php

namespace Drush\Commands\core;

use Drupal\image\Entity\ImageStyle;
use Drush\Commands\DrushCommands;

class ImageCommands extends DrushCommands {

  /**
   * Flush all derived images for a given style.
   *
   * @command image-flush
   * @param $style_names A comma delimited list of image style machine names. If not provided, user may choose from a list of names.
   * @option all Flush all derived images
   * @usage drush image-flush
   *   Pick an image style and then delete its derivatives.
   * @usage drush image-flush thumbnail,large
   *   Delete all thumbnail and large derivatives.
   * @usage drush image-flush --all
   *   Flush all derived images. They will be regenerated on the fly.
   * @validate-entity-load image_style style_names
   * @aliases if
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @todo @complete
   */
  public function flush($style_names = NULL, $options = ['all' => FALSE]) {
    foreach (ImageStyle::loadMultiple(_convert_csv_to_array($style_names)) as $style_name => $style) {
      $style->flush();
      $this->logger()->success(dt('Image style !style_name flushed', array('!style_name' => $style_name)));
    }
  }

  /**
   * @hook interact image-flush
   */
  public function interactFlush($input, $output) {
    $styles = ImageStyle::loadMultiple();
    $style_names = $input->getArgument('style_names');

    if (empty($input->getOption('all') && empty($style_names))) {
      $choices = array_combine($styles, $styles);
      $choices = array_merge(array('all' => 'all'), $choices);
      $style_names = drush_choice($choices, dt("Choose a style to flush."));
      if ($style_names === FALSE) {
        return drush_user_abort();
      }
    }

    if ($style_names == 'all') {
      $style_names = implode(',', array_keys($styles));
    }
    $input->setArgument('style_names', $style_names);
  }

  /**
   * Create an image derivative.
   *
   * @command image-derive
   * @param $style_name An image style machine name.
   * @param $source Path to a source image. Optionally prepend stream wrapper scheme.
   * @usage drush image-derive thumbnail core/themes/bartik/screenshot.png
   *   Save thumbnail sized derivative of logo image.
   * @validate-file-exists source
   * @validate-entity-load image_style style
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases id
   */
  public function derive($style_name, $source)  {
    $image_style = ImageStyle::load($style_name);
    $derivative_uri = $image_style->buildUri($source);
    if ($image_style->createDerivative($source, $derivative_uri)) {
      return $derivative_uri;
    }
  }

}