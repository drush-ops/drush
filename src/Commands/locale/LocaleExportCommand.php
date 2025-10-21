<?php

declare(strict_types=1);

namespace Drush\Commands\locale;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Exports to a gettext translation file.',
    aliases: ['locale-export'],
)]
#[CLI\ValidateModulesEnabled(modules: ['locale'])]
final class LocaleExportCommand extends Command
{
    use AutowireTrait;
    use LocaleTrait;

    const string NAME = 'locale:export';

    public function __construct(
        protected LanguageManagerInterface $languageManager,
        protected ConfigFactoryInterface $configFactory,
        protected ModuleHandlerInterface $moduleHandler,
        protected StateInterface $state,
        protected LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('langcode', InputArgument::OPTIONAL, 'The language code of the exported translations.')
            ->addOption('template', null, InputOption::VALUE_NONE, 'POT file output of extracted source texts to be translated.')
            ->addOption('types', null, InputOption::VALUE_REQUIRED, 'A comma separated list of string types to include, defaults to all types. Recognized values: not-customized, customized, not-translated')
            ->addUsage('locale:export nl > nl.po')
            ->addUsage('locale:export nl --types=customized,not-customized > nl.po')
            ->addUsage('locale:export --template > drupal.pot');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $this->validateExport($input);

        $langcode = $input->getArgument('langcode');
        $template = $input->getOption('template');
        $types = $input->getOption('types');

        $language = $this->getTranslatableLanguage($langcode);
        $poreader_options = [];

        if (!$template) {
            $poreader_options = $this->convertTypesToPoDbReaderOptions(StringUtils::csvToArray($types));
        }

        $file_uri = drush_tempnam('drush_', null, '.po');
        if ($this->writePoFile($file_uri, $language, $poreader_options)) {
            $output->writeln(file_get_contents($file_uri), OutputInterface::OUTPUT_RAW);
        } else {
            $io->success('Nothing to export.');
        }

        return self::SUCCESS;
    }

    public function validateExport(InputInterface $input): void
    {
        $langcode = $input->getArgument('langcode');
        $template = $input->getOption('template');
        $types = $input->getOption('types');

        if (!$langcode && !$template) {
            throw new InvalidArgumentException('Set LANGCODE or --template, see help for more information.');
        }
        if ($template && $types) {
            throw new InvalidArgumentException('Can not use both --types and --template, see help for more information.');
        }
    }
}
