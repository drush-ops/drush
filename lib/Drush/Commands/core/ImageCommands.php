<?php

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;

class ImageCommands extends DrushCommands {

  /**
   * Flush all derived images for a given style.
   *
   * @command image-flush
   * @param $style An image style machine name. If not provided, user may choose from a list of names.
   * @option all Flush all derived images
   * @usage drush image-flush
   *   Pick an image style and then delete its images.
   * @usage drush image-flush thumbnail
   *   Delete all thumbnail images.
   * @usage drush image-flush --all
   *   Flush all derived images. They will be regenerated on the fly.
   * @aliases if
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @todo @complete
   */
  public function flush($style = NULL, $options = ['all' => FALSE]) {
    drush_include_engine('drupal', 'image');
    if ($options['all']) {
      $style_name = 'all';
    }

    if (empty($style_name)) {
      $styles = array_keys(drush_image_styles());
      $choices = array_combine($styles, $styles);
      $choices = array_merge(array('all' => 'all'), $choices);
      $style_name = drush_choice($choices, dt("Choose a style to flush."));
      if ($style_name === FALSE) {
        return drush_user_abort();
      }
    }

    if ($style_name == 'all') {
      foreach (drush_image_styles() as $style_name => $style) {
        drush_image_flush_single($style_name);
      }
      $this->logger()->success(dt('All image styles flushed'));
    }
    else {
      drush_image_flush_single($style_name);
    }
  }

}