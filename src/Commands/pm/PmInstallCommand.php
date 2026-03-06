<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
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
        $error = false;
        foreach ($modules as $module) {
            // Note: we can't just call the API ($moduleHandler->loadInclude($module, 'install')),
            // because the API ignores modules that haven't been installed yet. We have
            // to do it the same way the `function drupal_check_module($module)` does.
            $file = DRUPAL_ROOT . '/' . $this->extensionListModule->getPath($module) . "/$module.install";
            if (is_file($file)) {
                require_once $file;
            }
            // Once we've loaded the module, we can invoke its requirements hook.
            $requirements = $this->moduleHandler->invoke($module, 'requirements', ['install']) ?? [];
            if (function_exists('install_check_class_requirements')) {
                $requirements = array_merge($requirements, install_check_class_requirements($this->extensionListModule->get($module)));
            }
            // @todo use Enum value instead of ints when we drop support for d11.
            if (is_array($requirements) && drush_drupal_requirements_severity($requirements) == 2) {
                $error = true;
                $reasons = [];
                foreach ($requirements as $id => $requirement) {
                    if (empty($requirement['severity'])) {
                        continue;
                    }
                    $value = $requirement['severity'];
                    if (is_object($requirement['severity'])) {
                        $value = $requirement['severity']->value;
                    }
                    if ($value !== REQUIREMENT_ERROR) {
                        continue;
                    }
                    $message = $requirement['description'];
                    if (isset($requirement['value']) && $requirement['value']) {
                        $message = sprintf('%s (Currently using %s version %s)', $requirement['description'], $requirement['title'], $requirement['value']);
                    }
                    $reasons[$id] = $message;
                }
                $this->logger->error(sprintf("Unable to install module '%s' due to unmet requirement(s):%s", $module, "\n  - " . implode("\n  - ", $reasons)));
            }
        }

        if ($error) {
            // Allow the user to bypass the install requirements.
            if (!$io->confirm(sprintf('The %s module\'s install requirements failed. Do you wish to continue?', $module), false)) {
                throw new UserAbortException();
            }
        }
    }
}
