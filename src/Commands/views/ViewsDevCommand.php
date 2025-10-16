<?php

declare(strict_types=1);

namespace Drush\Commands\views;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Set several Views settings to more developer-oriented values.',
    aliases: ['vd', 'views-dev']
)]
#[CLI\ValidateModulesEnabled(modules: ['views'])]
final class ViewsDevCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'views:dev';

    public function __construct(
        protected ConfigFactoryInterface $configFactory
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        $settings = [
            'ui.show.listing_filters' => true,
            'ui.show.master_display' => true,
            'ui.show.advanced_column' => true,
            'ui.always_live_preview' => false,
            'ui.always_live_preview_button' => true,
            'ui.show.preview_information' => true,
            'ui.show.sql_query.enabled' => true,
            'ui.show.sql_query.where' => 'above',
            'ui.show.performance_statistics' => true,
            'ui.show.additional_queries' => true,
        ];

        $config = $this->configFactory->getEditable('views.settings');

        foreach ($settings as $setting => $value) {
            $config->set($setting, $value);
            // Convert boolean values into a string to print.
            if (is_bool($value)) {
                $displayValue = $value ? 'TRUE' : 'FALSE';
            } else {
                $displayValue = $value;
            }

            $io->success(sprintf('%s set to %s', $setting, $displayValue));
        }

        // Save the new config.
        $config->save();

        $io->success('New views configuration saved.');

        return self::SUCCESS;
    }
}
