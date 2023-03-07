<?php

namespace Unish;

use Drush\Drupal\Commands\core\ImageCommands;
use Drush\Drupal\Commands\pm\PmCommands;

/**
 * Tests image-flush command
 *
 * @group commands
 */
class ImageTest extends UnishIntegrationTestCase
{
    public function testImage()
    {
        $this->drush(PmCommands::INSTALL, ['image']);
        $logo = 'core/misc/menu-expanded.png';
        $styles_dir = $this->webroot() . '/sites/default/files/styles/';
        $thumbnail = $styles_dir . 'thumbnail/public/' . $logo;
        $medium = $styles_dir . 'medium/public/' . $logo;

        // Remove stray files left over from previous runs
        @unlink($thumbnail);

        // Test that "drush image-derive" works.
        $style_name = 'thumbnail';
        $this->drush(ImageCommands::DERIVE, [$style_name, $logo]);
        $this->assertFileExists($thumbnail);

        // Test that "drush image-flush thumbnail" deletes derivatives created by the thumbnail image style.
        $this->drush(ImageCommands::FLUSH, [$style_name], ['all' => null]);
        $this->assertFileDoesNotExist($thumbnail);

        // Check that "drush image-flush --all" deletes all image styles by creating two different ones and testing its
        // existence afterwards.
        $this->drush(ImageCommands::DERIVE, ['thumbnail', $logo]);
        $this->assertFileExists($thumbnail);
        $this->drush(ImageCommands::DERIVE, ['medium', $logo]);
        $this->assertFileExists($medium);
        $this->drush(ImageCommands::FLUSH, [], ['all' => null]);
        $this->assertFileDoesNotExist($thumbnail);
        $this->assertFileDoesNotExist($medium);
    }
}
