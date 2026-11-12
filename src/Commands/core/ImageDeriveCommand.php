<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Create an image derivative',
    aliases: ['id', 'image-derive'],
    usages: [
        'image:derive thumbnail core/themes/bartik/screenshot.png',
    ],
)]
class ImageDeriveCommand
{
    use AutowireTrait;
    use ImageTrait;

    public const string NAME = 'image:derive';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected ModuleHandlerInterface $moduleHandler,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('An image style machine name.', suggestedValues: [self::class, 'suggestStyleNames'])]
        string $styleName,
        #[Argument('Path to a source image. Optionally prepend stream wrapper scheme. Relative paths calculated from Drupal root.')]
        string $source,
    ): int {
        $this->requireImageModule();
        $style = $this->loadStyle($styleName);
        if (!file_exists($source)) {
            throw new \InvalidArgumentException((string) $this->t('File(s) not found: @paths', ['@paths' => $source]));
        }

        $io = new SymfonyStyle($input, $output);
        $derivative_uri = $style->buildUri($source);
        if (!$style->createDerivative($source, $derivative_uri)) {
            $io->error((string) $this->t('Unable to create the derivative image: @uri', ['@uri' => $derivative_uri]));
            return Command::FAILURE;
        }
        $io->success((string) $this->t('Derivative image created: @uri', ['@uri' => $derivative_uri]));
        return Command::SUCCESS;
    }
}
