<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'All global options.',
    aliases: ['core-global-options'],
)]
#[CLI\FieldLabels(labels: ['name' => 'Name', 'description' => 'Description'])]
#[CLI\FilterDefaultField(field: 'name')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class CoreGlobalOptionsCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'core:global-options';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('All global options.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $def = $this->getApplication()->getDefinition();
        $rows = [];

        foreach ($def->getOptions() as $key => $value) {
            $name = '--' . $key;
            if ($value->getShortcut()) {
                $name = '-' . $value->getShortcut() . ', ' . $name;
            }
            $rows[] = [
                'name' => $name,
                'description' => $value->getDescription(),
            ];
        }

        // Also document the keys that are recognized by PreflightArgs. It would be possible to redundantly declare
        // those as global options. We don't do that for now, to avoid confusion.
        $ancient = [
            'config' => 'Specify an additional config file to load. See example.drush.yml. Example: /path/file',
            'alias-path' => 'Specifies additional paths where Drush will search for alias files. Example: /path/alias1:/path/alias2',
            'include' => 'Additional directories to search for Drush commands. Commandfiles should be placed in a subdirectory called Commands. Example: path/dir',
            'local' => 'Don\'t look outside the Composer project for Drush config.',
            'strict' => 'Return an error on unrecognized options. --strict=0 allows unrecognized options.',
            'ssh-options' => 'A string of extra options that will be passed to the ssh command. Example: -p 100',
        ];
        foreach ($ancient as $name => $description) {
            $rows[] = [
                'name' => '--' . $name,
                'description' => $description,
            ];
        }
        usort($rows, fn($a, $b) => strnatcmp($a['name'], $b['name']));

        return new RowsOfFields($rows);
    }
}
