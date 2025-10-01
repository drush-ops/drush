<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Drush;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Show Drush version.',
)]
#[CLI\TableFormat(listDelimiter: ':', tableStyle: 'compact')]
#[CLI\FieldLabels(labels: ['drush-version' => 'Drush version'])]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'table')]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class VersionCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'version';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $versionPropertyList = new PropertyList(['drush-version' => Drush::getVersion()]);
        $versionPropertyList->addRendererFunction(
            function ($key, $cellData, FormatterOptions $options) {
                if ($key == 'drush-version') {
                    return Drush::sanitizeVersionString($cellData);
                }
                return $cellData;
            }
        );

        return $versionPropertyList;
    }
}
