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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Imports the available translation updates.',
    aliases: ['locale-update'],
)]
#[CLI\ValidateModulesEnabled(modules: ['locale'])]
final class LocaleUpdateCommand extends Command
{
    use AutowireTrait;
    use LocaleTrait;

    const string NAME = 'locale:update';

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
            ->addOption('langcodes', null, InputOption::VALUE_REQUIRED, 'A comma-separated list of language codes to update. If omitted, all translations will be updated.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->moduleHandler->loadInclude('locale', 'fetch.inc');
        $this->moduleHandler->loadInclude('locale', 'bulk.inc');

        $langcodes = [];
        foreach (locale_translation_get_status() as $project_id => $project) {
            foreach ($project as $langcode => $project_info) {
                if (!empty($project_info->type) && !in_array($langcode, $langcodes)) {
                    $langcodes[] = $langcode;
                }
            }
        }

        if ($passed_langcodes = $input->getOption('langcodes')) {
            $langcodes = array_intersect($langcodes, explode(',', $passed_langcodes));
        }

        // Deduplicate the list of langcodes since each project may have added the
        // same language several times.
        $langcodes = array_unique($langcodes);

        $projects = [];

        // Set the translation import options. This determines if existing
        // translations will be overwritten by imported strings.
        $translationOptions = drush_locale_translation_default_update_options();

        // If the status was updated recently we can immediately start fetching the
        // translation updates. If the status is expired we clear it an run a batch to
        // update the status and then fetch the translation updates.
        $last_checked = $this->state->get('locale.translation_last_checked');
        if ($last_checked < time() - LOCALE_TRANSLATION_STATUS_TTL) {
            locale_translation_clear_status();
            $batch = locale_translation_batch_update_build([], $langcodes, $translationOptions);
            batch_set($batch);
        } else {
            // Set a batch to download and import translations.
            $batch = locale_translation_batch_fetch_build($projects, $langcodes, $translationOptions);
            batch_set($batch);
            // Set a batch to update configuration as well.
            if ($batch = locale_config_batch_update_components($translationOptions, $langcodes)) {
                batch_set($batch);
            }
        }

        drush_backend_batch_process();

        return self::SUCCESS;
    }
}
