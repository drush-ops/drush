<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Show a list of available extensions (modules and themes).',
    aliases: ['pml', 'pm-list'],
)]
#[CLI\FieldLabels(labels: [
    'package' => 'Package',
    'project' => 'Project',
    'display_name' => 'Name',
    'name' => 'Name',
    'type' => 'Type',
    'path' => 'Path',
    'status' => 'Status',
    'version' => 'Version',
])]
#[CLI\DefaultTableFields(fields: ['package', 'display_name', 'status', 'version'])]
#[CLI\FilterDefaultField(field: 'display_name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class PmListCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use PmTrait;

    const string NAME = 'pm:list';

    public function __construct(
        protected ConfigFactoryInterface $configFactory,
        protected ModuleHandlerInterface $moduleHandler,
        protected ThemeHandlerInterface $themeHandler,
        protected ModuleExtensionList $extensionListModule,
        protected ThemeExtensionList $extensionListTheme,
        protected LoggerInterface $logger,
        protected FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only show extensions having a given type. Choices: module, theme.', 'module,theme')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Only show extensions having a given status. Choices: enabled or disabled.', 'enabled,disabled')
            ->addOption('core', null, InputOption::VALUE_NONE, 'Only show extensions that are in Drupal core.')
            ->addOption('no-core', null, InputOption::VALUE_NONE, 'Only show extensions that are not provided by Drupal core.')
            ->addOption('package', null, InputOption::VALUE_REQUIRED, 'Only show extensions having a given project packages (e.g. Development).')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'table');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $rows = [];

        $modules = $this->extensionListModule->getList();
        $themes = $this->extensionListTheme->getList();
        $both = array_merge($modules, $themes);

        $package_filter = StringUtils::csvToArray(strtolower((string) $input->getOption('package')));
        $type_filter = StringUtils::csvToArray(strtolower($input->getOption('type')));
        $status_filter = StringUtils::csvToArray(strtolower($input->getOption('status')));

        foreach ($both as $key => $extension) {
            // Fill in placeholder values as needed.
            $extension->info += ['package' => ''];

            // Filter out test modules/themes.
            if (strpos($extension->getPath(), 'tests')) {
                continue;
            }

            $status = $this->extensionStatus($extension);
            if (!in_array($extension->getType(), $type_filter)) {
                unset($modules[$key]);
                continue;
            }
            if (!in_array($status, $status_filter)) {
                unset($modules[$key]);
                continue;
            }

            // Filter out core if --no-core specified.
            if ($input->getOption('no-core')) {
                if ($extension->origin == 'core') {
                    unset($modules[$key]);
                    continue;
                }
            }

            // Filter out non-core if --core specified.
            if ($input->getOption('core')) {
                if ($extension->origin != 'core') {
                    unset($modules[$key]);
                    continue;
                }
            }

            // Filter by package.
            if ($package_filter !== []) {
                if (!in_array(strtolower($extension->info['package']), $package_filter)) {
                    unset($modules[$key]);
                    continue;
                }
            }

            $row = [
                'package' => $extension->info['package'],
                'project' => $extension->info['project'] ?? '',
                'display_name' => $extension->info['name'] . ' (' . $extension->getName() . ')',
                'name' => $extension->getName(),
                'type' => $extension->getType(),
                'path' => $extension->getPath(),
                'status' => ucfirst($status),
                // Suppress notice when version is not present.
                'version' => @$extension->info['version'],
            ];
            $rows[$key] = $row;
        }

        return new RowsOfFields($rows);
    }
}
