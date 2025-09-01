<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Create an image derivative',
    aliases: ['id', 'image-derive'],
    usages: ['image:derive thumbnail core/themes/bartik/screenshot.png']
)]
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
#[CLI\ValidateModulesEnabled(['image'])]
#[CLI\ValidateEntityLoad(entityType: 'image_style', argumentName: 'style-name')]
#[CLI\ValidateFileExists('source')]
final class ImageDeriveCommand
{
    use AutowireTrait;

    public const NAME = 'image:derive';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly ModuleHandlerInterface $moduleHandler
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('An image style machine name.')]
        string $style_name,
        #[Argument('Path to a source image. Optionally prepend stream wrapper scheme. Relative paths calculated from Drupal root.')]
        string $source,
    ): int {
        $io = new DrushStyle($input, $output);

        $image_style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
        $derivative_uri = $image_style->buildUri($source);
        if ($image_style->createDerivative($source, $derivative_uri)) {
            $io->success(dt('Derivative image created: !uri', ['!uri' => $derivative_uri]));
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }
}
