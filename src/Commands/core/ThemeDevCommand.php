<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'Toggle Twig development and cache aggregation settings',
    aliases: ['thdev'],
)]
#[CLI\Version(version: '13.6')]
class ThemeDevCommand extends Command
{
    use AutowireTrait;

    const NAME = 'theme:dev';

    protected function __construct(
        // @todo Can we avoid the autowire attribute here?
        #[Autowire(service: 'keyvalue')]
        protected KeyValueFactoryInterface $keyValueFactory,
        protected ConfigFactoryInterface $configFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(name: 'mode', mode: InputArgument::REQUIRED, description: '"on" or "off"', suggestedValues: ['on', 'off'])
            ->addUsage('drush theme:dev on')
            ->addUsage('drush theme:dev off')
            ->setHelp('When enabled:
     * - Disables render cache, dynamic page cache, and page cache.
     * - Enables Twig debug mode (e.g., `dump()` function, template suggestions).
     * - Disables Twig cache (templates always recompiled).
     * - Disables CSS and JS aggregation.
     *
     * When disabled, restores default performance-oriented settings.
     *
     * Clears all Drupal caches to apply changes immediately.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $devMode = match ($input->getArgument('mode')) {
            'on' => true,
            'off' => false,
            default => throw new \InvalidArgumentException("Invalid mode. Use 'on' or 'off'."),
        };

        $this->keyValueFactory->get('development_settings')->setMultiple([
            'disable_rendered_output_cache_bins' => $devMode,
            'twig_debug' => $devMode,
            'twig_cache_disable' => $devMode,
        ]);

        $this->configFactory->getEditable('system.performance')
            ->set('css.preprocess', !$devMode)
            ->set('js.preprocess', !$devMode)
            ->save();

        drupal_flush_all_caches();

        (new DrushStyle($input, $output))->success(sprintf(
            'Developer mode %s: CSS/JS aggregation %s, Twig debug settings %s.',
            $devMode ? 'enabled' : 'disabled',
            $devMode ? 'disabled' : 'enabled',
            $devMode ? 'enabled' : 'disabled'
        ));
        return Command::SUCCESS;
    }
}
