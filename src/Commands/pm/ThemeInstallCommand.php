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
    description: 'Install one or more themes.',
    aliases: ['thin', 'theme:enable', 'then', 'theme-enable'],
)]
final class ThemeInstallCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'theme:install';

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

        if (!$this->themeInstaller->install($themes)) {
            throw new \Exception('Unable to install themes.');
        }

        $io->success(sprintf('Successfully installed theme: %s', implode(', ', $themes)));

        return self::SUCCESS;
    }
}
