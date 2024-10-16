<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\Validators;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Flush all derived images for a given style.',
    aliases: ['if', 'image-flush']
)]
#[CLI\ValidateModulesEnabled(modules: ['image'])]
final class ImageFlushCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'image:flush';

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->addArgument('style_names', InputArgument::OPTIONAL, 'A comma delimited list of image style machine names. If not provided, user may choose from a list of names.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Flush all derived images')
            ->addUsage('image:flush thumbnail,large')
            ->addUsage('image:flush --all')
            ->setHelp('Immediately before running this command, web crawl your entire web site. Or use your Production PHPStorage dir for comparison.');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new DrushStyle($input, $output);
        $styles = array_keys($this->entityTypeManager->getStorage('image_style')->loadMultiple());
        $style_names = $input->getArgument('style_names');

        if (empty($style_names) && !$input->getOption('all')) {
            $styles_all = $styles;
            array_unshift($styles_all, 'all');
            $choices = array_combine($styles_all, $styles_all);
            $style_names = $io->choice(dt("Choose a style to flush"), $choices, 'all');
            if ($style_names == 'all') {
                $style_names = implode(',', $styles);
            }
            $input->setArgument('style_names', $style_names);
        }
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($names = $input->getArgument('style_names')) {
            Validators::entityLoad(StringUtils::csvToArray($names), 'image_style');
        }

        // Needed for non-interactive requests.
        if ($input->getOption('all')) {
            $input->setArgument('style_names', implode(',', array_keys(ImageStyle::loadMultiple())));
        }

        $ids = StringUtils::csvToArray($input->getArgument('style_names'));
        foreach ($this->entityTypeManager->getStorage('image_style')->loadMultiple($ids) as $style_name => $style) {
            $style->flush();
            $io = new DrushStyle($input, $output);
            $io->success("Image style $style_name flushed");
        }
        return static::SUCCESS;
    }
}
