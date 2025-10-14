<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Uninstall one or more modules and their dependent modules.',
    aliases: ['un', 'pmu', 'pm-uninstall'],
)]
final class PmUninstallCommand extends Command
{
    use AutowireTrait;
    use PmTrait;

    const string NAME = 'pm:uninstall';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected ModuleInstallerInterface $moduleInstaller,
        protected ModuleHandlerInterface $moduleHandler,
        protected ModuleExtensionList $extensionListModule,
        protected LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('modules', InputArgument::IS_ARRAY, 'A comma delimited list of modules.')
            ->addUsage('pm:uninstall --simulate field_ui');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $modules = $input->getArgument('modules');
        $modules = StringUtils::csvToArray($modules);

        $installed_modules = array_filter($modules, function ($module) {
            return $this->moduleHandler->moduleExists($module);
        });
        if ($installed_modules === []) {
            throw new \Exception(sprintf('The following module(s) are not installed: %s. No modules to uninstall.', implode(', ', $modules)));
        }
        if ($installed_modules !== $modules) {
            $this->logger->warning('The following module(s) are not installed and will not be uninstalled: {list}', ['list' => implode(', ', array_diff($modules, $installed_modules))]);
        }

        $list = $this->addUninstallDependencies($installed_modules);
        if (Drush::simulate()) {
            $output->writeln(sprintf('The following extensions will be uninstalled: %s', implode(', ', $list)));
            return self::SUCCESS;
        }

        if (array_values($list) !== $modules) {
            $output->writeln(sprintf('The following extensions will be uninstalled: %s', implode(', ', $list)));
            if (!$io->confirm('Do you want to continue?')) {
                throw new UserAbortException();
            }
        }
        if (!$this->moduleInstaller->uninstall($modules, true)) {
            throw new \Exception('Unable to uninstall modules.');
        }
        $io->success(sprintf('Successfully uninstalled: %s', implode(', ', $list)));

        return self::SUCCESS;
    }

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::NAME)]
    public function validateUninstall(CommandData $commandData): void
    {
        $list = [];
        if ($modules = $commandData->input()->getArgument('modules')) {
            $modules = StringUtils::csvToArray($modules);
            if ($validation_reasons = $this->moduleInstaller->validateUninstall($modules)) {
                foreach ($validation_reasons as $module => $reasons) {
                    // @phpstan-ignore foreach.nonIterable
                    foreach ($reasons as $reason) {
                        $list[] = "$module: " . (string)$reason;
                    }
                }
                throw new \Exception(implode("\n", $list));
            }
        }
    }
}
