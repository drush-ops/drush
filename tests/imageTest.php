<?php

namespace Unish;

/**
 * Tests image-flush command
 *
 * @group commands
 */
class ImageCase extends CommandUnishTestCase {

  function testImage() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => $this->getUri(),
    );
    $this->drush('pm-enable', ['image'], $options);
    $logo = 'core/themes/bartik/screenshot.png';
    $styles_dir = $options['root'] . '/sites/' . $this->getUri() . '/files/styles/';
    $thumbnail = $styles_dir . 'thumbnail/public/' . $logo;
    $medium = $styles_dir . 'medium/public/' . $logo;

    // Test that "drush image-derive" works.
    $style_name = 'thumbnail';
    $this->drush('image-derive', array($style_name, $logo), $options);
    $this->assertFileExists($thumbnail);

    // Test that "drush image-flush thumbnail" deletes derivatives created by the thumbnail image style.
    $this->drush('image-flush', array($style_name), $options + ['all' => NULL]);
    $this->assertFileNotExists($thumbnail);

    // Check that "drush image-flush --all" deletes all image styles by creating two different ones and testing its
    // existence afterwards.
    $this->drush('image-derive', array('thumbnail', $logo), $options);
    $this->assertFileExists($thumbnail);
    $this->drush('image-derive', array('medium', $logo), $options);
    $this->assertFileExists($medium);
    $this->drush('image-flush', array(), array('all' => null) + $options);
    $this->assertFileNotExists($thumbnail);
    $this->assertFileNotExists($medium);
  }
}
