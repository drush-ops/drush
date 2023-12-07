<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\image\Entity\ImageStyle;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Boot\DrupalBootLevels;

final class ImageCommands extends DrushCommands
{
    const FLUSH = 'image:flush';
    const DERIVE = 'image:derive';

    /**
     * Flush all derived images for a given style.
     */
    #[CLI\Command(name: self::FLUSH, aliases: ['if', 'image-flush'])]
    #[CLI\Argument(name: 'style_names', description: 'A comma delimited list of image style machine names. If not provided, user may choose from a list of names.')]
    #[CLI\Option(name: 'all', description: 'Flush all derived images')]
    #[CLI\Usage(name: 'drush image:flush', description: 'Pick an image style and then delete its derivatives.')]
    #[CLI\Usage(name: 'drush image:flush thumbnail,large', description: 'Delete all thumbnail and large derivatives.')]
    #[CLI\Usage(name: 'drush image:flush --all', description: 'Flush all derived images. They will be regenerated on demand.')]
    #[CLI\ValidateEntityLoad(entityType: 'image_style', argumentName: 'style_names')]
    #[CLI\ValidateModulesEnabled(modules: ['image'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function flush($style_names, $options = ['all' => false]): void
    {
        foreach (ImageStyle::loadMultiple(StringUtils::csvToArray($style_names)) as $style_name => $style) {
            $style->flush();
            $this->logger()->success(dt('Image style !style_name flushed', ['!style_name' => $style_name]));
        }
    }

    #[CLI\Hook(type: HookManager::INTERACT, target: self::FLUSH)]
    public function interactFlush(InputInterface $input, $output): void
    {
        $styles = array_keys(ImageStyle::loadMultiple());
        $style_names = $input->getArgument('style_names');

        if (empty($style_names)) {
            $styles_all = $styles;
            array_unshift($styles_all, 'all');
            $choices = array_combine($styles_all, $styles_all);
            $style_names = $this->io()->choice(dt("Choose a style to flush"), $choices, 'all');
            if ($style_names == 'all') {
                $style_names = implode(',', $styles);
            }
            $input->setArgument('style_names', $style_names);
        }
    }

    #[CLI\Hook(type: HookManager::POST_INITIALIZE, target: self::FLUSH)]
    public function postInit(InputInterface $input, AnnotationData $annotationData): void
    {
        // Needed for non-interactive calls.We use post-init phase because interact() methods run early
        if ($input->getOption('all')) {
            $styles = array_keys(ImageStyle::loadMultiple());
            $input->setArgument('style_names', implode(",", $styles));
        }
    }

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
