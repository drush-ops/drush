<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Flush all derived images for a given style.',
    aliases: ['if', 'image-flush'],
    usages: [
        'image:flush thumbnail,large',
        'image:flush --all',
    ],
)]
class ImageFlushCommand
{
    use AutowireTrait;
    use ImageTrait;

    public const string NAME = 'image:flush';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected ModuleHandlerInterface $moduleHandler,
    ) {
    }

    /**
     * Prompts for the styles to flush when neither names nor --all were given.
     */
    #[Interact]
    public function askForStyles(InputInterface $input, OutputInterface $output): void
    {
        if (!empty($input->getArgument('style-names')) || $input->getOption('all')) {
            return;
        }
        $styles = $this->allStyleNames();
        $choices = array_merge(['all'], $styles);
        $io = new SymfonyStyle($input, $output);
        $choice = $io->choice((string) $this->t('Choose a style to flush'), array_combine($choices, $choices), 'all');
        $input->setArgument('style-names', $choice === 'all' ? implode(',', $styles) : $choice);
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument('A comma delimited list of image style machine names. If not provided, user may choose from a list of names.', suggestedValues: [self::class, 'suggestStyleNames'])]
        ?string $styleNames = null,
        #[Option('Flush all derived images')]
        bool $all = false,
    ): int {
        $this->requireImageModule();
        if ($all) {
            $styles = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
        } else {
            $names = StringUtils::csvToArray($styleNames);
            if ($names === []) {
                throw new \InvalidArgumentException((string) $this->t('Specify image style names to flush, or --all.'));
            }
            $styles = $this->loadStyles($names);
        }

        $io = new SymfonyStyle($input, $output);
        foreach ($styles as $name => $style) {
            $style->flush();
            $io->success((string) $this->t('Image style @name flushed.', ['@name' => $name]));
        }
        return Command::SUCCESS;
    }
}
