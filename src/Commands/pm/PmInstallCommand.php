<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Enable one or more modules.',
    aliases: ['in', 'install', 'pm-install', 'en', 'pm-enable', 'pm:enable'],
)]
final class PmInstallCommand extends Command
{
    use AutowireTrait;
    use PmTrait;

    const string NAME = 'pm:install';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected MessengerInterface $messenger,
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
            ->addOption(name: 'dry-run', mode: InputOption::VALUE_NONE, description: 'Outputs the operations but will not execute anything.')
            ->addUsage('pm:install --dry-run content_moderation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $this->validateModules($input, $io);

        $modules = $input->getArgument('modules');
        $modules = StringUtils::csvToArray($modules);
        $todo = $this->addInstallDependencies($modules);

        if ($todo === []) {
            $this->logger->notice('Already installed: {list}', ['list' => implode(', ', $modules)]);
            return self::SUCCESS;
        } elseif ($input->getOption('dry-run')) {
            $output->writeln(sprintf('The following module(s) will be installed: %s', implode(', ', $todo)));
            return self::SUCCESS;
        } elseif (array_values($todo) !== $modules) {
            $output->writeln(sprintf('The following module(s) will be installed: %s', implode(', ', $todo)));
            if (!$io->confirm('Do you want to continue?')) {
                throw new UserAbortException();
            }
        }

        if (!$this->moduleInstaller->install($modules, true)) {
            throw new \Exception('Unable to install modules.');
        }
        if (batch_get()) {
            drush_backend_batch_process();
        }

        $moduleData = $this->extensionListModule->getList();
        foreach ($todo as $moduleName) {
            $links = $this->getModuleLinks($moduleData[$moduleName]);
            $links = array_map(fn($link) => sprintf('<href=%s>%s</>', $link->getUrl()->setAbsolute()->toString(), $link->getText()), $links);

            if ($links) {
                $this->logger->notice('Module links: {list}', ['list' => implode(', ', $links)]);
            }
            $io->success(sprintf('Module %s has been installed.', $moduleName));
        }

        return self::SUCCESS;
    }

    public function validateModules(InputInterface $input, DrushStyle $io): void
    {
        $modules = $input->getArgument('modules');
        $modules = StringUtils::csvToArray($modules);
        $modules = $this->addInstallDependencies($modules);
        if ($modules === []) {
            return;
        }

        require_once DRUPAL_ROOT . '/core/includes/install.inc';
        $errors = [];
        foreach ($modules as $module) {
            $return = drupal_check_module($module);
            if (!$return) {
                $errors = $this->messenger->messagesByType($this->messenger::TYPE_ERROR);
                $this->messenger->deleteByType($this->messenger::TYPE_ERROR);
                foreach ($errors as $error) {
                    $this->logger->error((string)$error);
                }
            }
        }

        if ($errors) {
            // Allow the user to bypass the install requirements.
            if (!$io->confirm('Install requirements failed. Do you wish to continue?', false)) {
                throw new UserAbortException();
            }
        }
    }
}
