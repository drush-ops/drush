<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Uninstall themes.',
    aliases: ['theme:un', 'thun', 'theme-uninstall'],
)]
final class ThemeUninstallCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'theme:uninstall';

    public function __construct(
        private readonly ThemeInstallerInterface $themeInstaller,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('themes', InputArgument::IS_ARRAY, 'A comma delimited list of themes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $themes = $input->getArgument('themes');
        $themes = StringUtils::csvToArray($themes);

        // The uninstall() method has no return value. Assume it succeeded, and
        // allow exceptions to bubble.
        $this->themeInstaller->uninstall($themes);

        $io->success(sprintf('Successfully uninstalled theme: %s', implode(', ', $themes)));

        return self::SUCCESS;
    }
}
