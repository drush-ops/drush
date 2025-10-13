<?php

declare(strict_types=1);

namespace Drush\Commands\views;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'views:list',
    description: 'Get a list of all views in the system.',
    aliases: ['vl', 'views-list']
)]
#[CLI\FieldLabels(labels: [
    'machine-name' => 'Machine name',
    'label' => 'Name',
    'description' => 'Description',
    'status' => 'Status',
    'tag' => 'Tag',
])]
#[CLI\DefaultTableFields(fields: ['machine-name', 'label', 'description', 'status'])]
#[CLI\ValidateModulesEnabled(modules: ['views'])]
#[CLI\FilterDefaultField(field: 'machine_name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class ViewsListCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'views:list';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
        protected EntityTypeManagerInterface $entityTypeManager,
        protected readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Filter views by name.')
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'A comma-separated list of views tags by which to filter the results.')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter views by status. Choices: enabled, disabled.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format the result data. Available formats: csv,json,list,null,php,print-r,sections,string,table,tsv,var_dump,var_export,xml,yaml', 'table')
            ->addUsage('vl --name=blog')
            ->addUsage('vl --tags=tag1,tag2')
            ->addUsage('vl --status=enabled');

        $this->setHelp('Get a list of all views in the system. Examples: Show a list of all available views. Show a list of views which names contain \'blog\'. Show a list of views tagged with \'tag1\' or \'tag2\'. Show a list of enabled views.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $io = new DrushStyle($input, $output);
        $nameOption = $input->getOption('name');
        $tagsOption = $input->getOption('tags');
        $statusOption = $input->getOption('status');

        $disabled_views = [];
        $enabled_views = [];

        $views = $this->entityTypeManager->getStorage('view')->loadMultiple();

        // Get the --name option.
        $name = StringUtils::csvToArray($nameOption);
        $with_name = $name !== [];

        // Get the --tags option.
        $tags =  StringUtils::csvToArray($tagsOption);
        $with_tags = $tags !== [];

        // Get the --status option. Store user input apart to reuse it after.
        $status = $statusOption;

        if ($status && !in_array($status, ['enabled', 'disabled'])) {
            throw new \Exception(sprintf('Invalid status: %s. Available options are "enabled" or "disabled"', $status));
        }

        // Setup a row for each view.
        foreach ($views as $view) {
            // If options were specified, check that first mismatch push the loop to the
            // next view.
            if ($with_name && !stristr($view->id(), $name[0])) {
                continue;
            }
            if ($with_tags && !in_array($view->get('tag'), $tags)) {
                continue;
            }

            $status_bool = $status == 'enabled';
            if ($status && ($view->status() !== $status_bool)) {
                continue;
            }

            $row = [
            'machine-name' => $view->id(),
            'label' => $view->label(),
            'description' =>  $view->get('description'),
            'status' =>  $view->status() ? 'Enabled' : 'Disabled',
            'tag' =>  $view->get('tag'),
            ];

            // Place the row in the appropriate array, so we can have disabled views at
            // the bottom.
            if ($view->status()) {
                  $enabled_views[] = $row;
            } else {
                  $disabled_views[] = $row;
            }
        }

        // Sort alphabetically.
        asort($disabled_views);
        asort($enabled_views);

        if (count($enabled_views) || count($disabled_views)) {
            $rows = array_merge($enabled_views, $disabled_views);
        } else {
            $this->logger->notice('No views found.');
            $rows = [];
        }
        return new RowsOfFields($rows);
    }
}
