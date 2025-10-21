<?php

declare(strict_types=1);

namespace Drush\Commands\locale;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
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
    description: 'Imports multiple translation files from the defined directory.',
    aliases: ['locale-import-all', 'locale:import:all'],
)]
#[CLI\Version(version: '12.2')]
#[CLI\ValidateModulesEnabled(modules: ['locale'])]
final class LocaleImportAllCommand extends Command
{
    use AutowireTrait;
    use LocaleTrait;

    const string NAME = 'locale:import-all';

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
            ->addArgument('directory', InputArgument::REQUIRED, 'The path to directory with translation files to import.')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'String types to include, defaults to not-customized. Recognized values: not-customized, customized')
            ->addOption('override', null, InputOption::VALUE_REQUIRED, 'Whether and how imported strings will override existing translations. Defaults to the Import behavior configured in the admin interface. Recognized values: none, customized, not-customized, all')
            ->addUsage('locale:import-all /var/www/translations')
            ->addUsage('locale:import-all /var/www/translations/custom --type=customized --override=all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');
        $type = $input->getOption('type');
        $override = $input->getOption('override');

        if (!is_dir($directory)) {
            throw new \Exception('The defined directory does not exist.');
        }

        // Look for .po files in defined directory
        $poFiles = glob($directory . DIRECTORY_SEPARATOR . '*.po');
        if (empty($poFiles)) {
            throw new \Exception('Translation files not found in the defined directory.');
        }

        $this->moduleHandler->loadInclude('locale', 'translation.inc');
        $this->moduleHandler->loadInclude('locale', 'bulk.inc');

        $translationOptions = _locale_translation_default_update_options();
        $translationOptions['customized'] = $this->convertCustomizedType($type);
        $override_options = $this->convertOverrideOption($override);
        if ($override_options) {
            $translationOptions['overwrite_options'] = $override_options;
        }

        $langcodes_to_import = [];
        $files = [];
        foreach ($poFiles as $file) {
            // Ensure we have the file intended for upload.
            if (!file_exists($file)) {
                $this->logger->warning('Can not read file {file}.', ['file' => $file]);
                continue;
            }
            $poFile = (object) [
                'filename' => basename($file),
                'uri' => $file,
            ];
            $poFile = locale_translate_file_attach_properties($poFile, $translationOptions);
            if ($poFile->langcode == LanguageInterface::LANGCODE_NOT_SPECIFIED) {
                $this->logger->warning('Can not autodetect language of file {file}. Supported filename patterns are: {project}-{version}.{langcode}.po, {prefix}.{langcode}.po or {langcode}.po.', [
                   'file' => $file,
                ]);
                continue;
            }
            if (!$this->languageManager->getLanguage($poFile->langcode)) {
                $this->logger->warning('Language {language} does not exist for file {file}', [
                    'language' => $poFile->langcode,
                    'file' => $file,
                ]);
                continue;
            }
            // Import translation file if language exists.
            $langcodes_to_import[$poFile->langcode] = $poFile->langcode;
            $files[$poFile->uri] = $poFile;
        }

        // Set a batch to download and import translations.
        $batch = locale_translate_batch_build($files, $translationOptions);
        batch_set($batch);
        if ($batch = locale_config_batch_update_components($translationOptions, $langcodes_to_import)) {
            batch_set($batch);
        }

        drush_backend_batch_process();

        return self::SUCCESS;
    }
}
