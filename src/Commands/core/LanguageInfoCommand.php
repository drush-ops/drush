<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Language\LanguageManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Print the currently available languages.',
    aliases: ['language-info'],
    hidden: true
)]
#[CLI\FieldLabels(labels: [
    'language' => 'Language',
    'direction' => 'Direction',
    'default' => 'Default',
    'locked' => 'Locked',
])]
#[CLI\DefaultTableFields(fields: ['language', 'direction', 'default'])]
#[CLI\FilterDefaultField(field: 'language')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class LanguageInfoCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const string NAME = 'language:info';

    public function __construct(
        protected readonly LanguageManagerInterface $languageManager,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Print the currently available languages with their direction, default status, and locked status.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute();
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(): RowsOfFields
    {
        $rows = [];
        $languages = $this->languageManager->getLanguages();

        foreach ($languages as $key => $language) {
            $row = [
                'language' => $language->getName() . ' (' . $language->getId() . ')',
                'direction' => $language->getDirection(),
                'default' => $language->isDefault() ? 'yes' : '',
                'locked' => $language->isLocked() ? 'yes' : '',
            ];
            $rows[$key] = $row;
        }

        return new RowsOfFields($rows);
    }
}
