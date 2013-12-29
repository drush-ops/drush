<?php
/**
 * @file
 *   Tests image-flush command
 *
 * @group commands
 */
class ImageCase extends Drush_CommandTestCase {

  function testImage() {
    if (UNISH_DRUPAL_MAJOR_VERSION == 6) {
      $this->markTestSkipped("Image styles not available in Drupal 6 core.");
    }

    $sites = $this->setUpDrupal(1, TRUE, UNISH_DRUPAL_MAJOR_VERSION, 'standard');
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );
    // Test that "drush image-flush thumbnail" deletes derivatives created by the thumbnail image style.
    $style_name = 'thumbnail';
    $php = "\$image_style = image_style_load('--stylename--');";
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $logo = $options['root'] . '/core/themes/bartik/logo.png';
      $php .= "return \$image_style->createDerivative('$logo', 'public://styles/--stylename--/logo.png');";
    }
    else {
      $logo = $options['root'] . '/themes/bartik/logo.png';
      $php .= "return image_style_create_derivative(\$image_style, '$logo', 'public://styles/--stylename--/logo.png');";
    }

    $this->drush('php-eval', array(str_replace('--stylename--', $style_name, $php)), $options);
    $this->assertFileExists($options['root'] . '/sites/' . key($sites) . '/files/styles/' . $style_name . '/logo.png');
    $this->drush('image-flush', array($style_name), $options);
    $this->assertFileNotExists($options['root'] . '/sites/' . key($sites) . '/files/styles/' . $style_name . '/logo.png');

    // Check that "drush image-flush --all" deletes all image styles by creating two different ones and testing its
    // existance afterwards.
    $style_name = 'thumbnail';
    $this->drush('php-eval', array(str_replace('--stylename--', $style_name, $php)), $options);
    $this->assertFileExists($options['root'] . '/sites/' . key($sites) . '/files/styles/' . $style_name . '/logo.png');
    $style_name = 'medium';
    $this->drush('php-eval', array(str_replace('--stylename--', $style_name, $php)), $options);
    $this->assertFileExists($options['root'] . '/sites/' . key($sites) . '/files/styles/' . $style_name . '/logo.png');
    $this->drush('image-flush', array(), array('all' => TRUE) + $options);
    $this->assertFileNotExists($options['root'] . '/sites/' . key($sites) . '/files/styles/thumbnail/logo.png');
    $this->assertFileNotExists($options['root'] . '/sites/' . key($sites) . '/files/styles/medium/logo.png');
  }
}
