<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\image\Entity\ImageStyle;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

final class ImageCommands extends DrushCommands
{
    const DERIVE = 'image:derive';

    /**
     * Create an image derivative.
     */
    #[CLI\Command(name: self::DERIVE, aliases: ['id', 'image-derive'])]
    #[CLI\Argument(name: 'style_name', description: 'An image style machine name.')]
    #[CLI\Argument(name: 'source', description: 'Path to a source image. Optionally prepend stream wrapper scheme. Relative paths calculated from Drupal root.')]
    #[CLI\Usage(name: 'drush image:derive thumbnail core/themes/bartik/screenshot.png', description: 'Save thumbnail sized derivative of logo image.')]
    #[CLI\ValidateFileExists(argName: 'source')]
    #[CLI\ValidateEntityLoad(entityType: 'image_style', argumentName: 'style_name')]
    #[CLI\ValidateModulesEnabled(modules: ['image'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function derive($style_name, $source)
    {
        $image_style = ImageStyle::load($style_name);
        $derivative_uri = $image_style->buildUri($source);
        if ($image_style->createDerivative($source, $derivative_uri)) {
            return $derivative_uri;
        }
    }
}
