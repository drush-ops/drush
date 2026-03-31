<?php

declare(strict_types=1);

namespace Drush\Commands\locale;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Imports from a gettext translation file.',
    aliases: ['locale-import'],
)]
#[CLI\ValidateModulesEnabled(modules: ['locale'])]
final class LocaleImportCommand extends Command
{
    use AutowireTrait;
    use LocaleTrait;

    const string NAME = 'locale:import';

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
            ->addArgument('langcode', InputArgument::REQUIRED, 'The language code of the imported translations.')
            ->addArgument('file', InputArgument::REQUIRED, 'Path and file name of the gettext file. Relative paths calculated from Drupal root.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Treat imported strings as this type of translation. Recognized values: not-customized, customized, not-translated', 'not-customized')
            ->addOption('override', null, InputOption::VALUE_REQUIRED, 'Whether and how imported strings will override existing translations. Defaults to the import behavior configured in the admin interface. Recognized values: none, customized, not-customized, all')
            ->addOption('autocreate-language', null, InputOption::VALUE_NONE, 'Create the language in addition to import.')
            ->addUsage('locale-import nl drupal-8.4.2.nl.po')
            ->addUsage('locale-import --type=customized nl drupal-8.4.2.nl.po')
            ->addUsage('locale-import --override=none nl drupal-8.4.2.nl.po')
            ->addUsage('locale-import --override=not-customized nl drupal-8.4.2.nl.po')
            ->addUsage('locale-import nl custom-translations.po --type=customized --override=all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $langcode = $input->getArgument('langcode');
        $file = $input->getArgument('file');
        $type = $input->getOption('type');
        $override = $input->getOption('override');
        $autocreate_language = $input->getOption('autocreate-language');

        if (!drush_file_not_empty($file)) {
            throw new \Exception(sprintf('File %s not found or empty.', $file));
        }

        $language = $this->getTranslatableLanguage($langcode, $autocreate_language);

        $this->moduleHandler->loadInclude('locale', 'translation.inc');
        $this->moduleHandler->loadInclude('locale', 'bulk.inc');

        $translationOptions = drush_locale_translation_default_update_options();
        $translationOptions['langcode'] = $language->getId();
        $translationOptions['customized'] = $this->convertCustomizedType($type);
        $override_options = $this->convertOverrideOption($override);
        if ($override_options) {
            $translationOptions['overwrite_options'] = $override_options;
        }

        $poFile = (object) [
            'filename' => basename($file),
            'uri' => $file,
        ];
        $poFile = locale_translate_file_attach_properties($poFile, $translationOptions);

        // Set a batch to download and import translations.
        $batch = locale_translate_batch_build([$poFile->uri => $poFile], $translationOptions);
        batch_set($batch);
        if ($batch = locale_config_batch_update_components($translationOptions, [$language->getId()])) {
            batch_set($batch);
        }

        drush_backend_batch_process();

        return self::SUCCESS;
    }
}
