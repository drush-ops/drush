<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\image\Entity\ImageStyle;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\InputInterface;

class ImageCommands extends DrushCommands
{
    /**
     * Flush all derived images for a given style.
     *
     * @command image:flush
     * @param $style_names A comma delimited list of image style machine names. If not provided, user may choose from a list of names.
     * @option all Flush all derived images
     * @usage drush image:flush
     *   Pick an image style and then delete its derivatives.
     * @usage drush image:flush thumbnail,large
     *   Delete all thumbnail and large derivatives.
     * @usage drush image:flush --all
     *   Flush all derived images. They will be regenerated on demand.
     * @validate-entity-load image_style style_names
     * @validate-module-enabled image
     * @aliases if,image-flush
     */
    public function flush($style_names, $options = ['all' => false]): void
    {
        foreach (ImageStyle::loadMultiple(StringUtils::csvToArray($style_names)) as $style_name => $style) {
            $style->flush();
            $this->logger()->success(dt('Image style !style_name flushed', ['!style_name' => $style_name]));
        }
    }

    /**
     * @hook interact image-flush
     */
    public function interactFlush($input, $output): void
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

    /**
     * @hook init image-flush
     */
    public function initFlush(InputInterface $input, AnnotationData $annotationData): void
    {
        // Needed for non-interactive calls.
        if ($input->getOption('all')) {
            $styles = array_keys(ImageStyle::loadMultiple());
            $input->setArgument('style_names', implode(",", $styles));
        }
    }

    /**
     * Create an image derivative.
     *
     * @command image:derive
     * @param $style_name An image style machine name.
     * @param $source Path to a source image. Optionally prepend stream wrapper scheme.
     * @usage drush image:derive thumbnail core/themes/bartik/screenshot.png
     *   Save thumbnail sized derivative of logo image.
     * @validate-file-exists source
     * @validate-entity-load image_style style_name
     * @validate-module-enabled image
     * @aliases id,image-derive
     */
    public function derive($style_name, $source)
    {
        $image_style = ImageStyle::load($style_name);
        $derivative_uri = $image_style->buildUri($source);
        if ($image_style->createDerivative($source, $derivative_uri)) {
            return $derivative_uri;
        }
    }
}
