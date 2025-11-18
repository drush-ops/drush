<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
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
    use PmTrait;

    const string NAME = 'theme:install';

    public function __construct(
        private readonly ThemeInstallerInterface $themeInstaller,
        private readonly ModuleInstallerInterface $moduleInstaller,
        private readonly ThemeExtensionList $extensionListTheme,
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

        $todo = $this->addInstallDependencies($themes, 'themes');
        $todo_str = ['!list' => implode(', ', $todo)];
        if (!empty($todo)) {
            $output->writeln(dt('The following module(s) and themes(s) will be installed: !list', $todo_str));
            if (!$io->confirm(dt('Do you want to continue?'))) {
                throw new UserAbortException();
            }

            $modules = array_diff(array_values($todo), array_values($themes));
            if (!empty($modules)) {
                if (!$this->moduleInstaller->install($modules, true)) {
                    throw new \Exception('Unable to install modules.');
                }
            }
        }

        if (!$this->themeInstaller->install($themes, true)) {
            throw new \Exception('Unable to install themes.');
        }

        $io->success(sprintf('Successfully installed theme: %s', implode(', ', $themes)));

        return self::SUCCESS;
    }
}
