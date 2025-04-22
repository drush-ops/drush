<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ThemeDevCommands extends DrushCommands
{
    use AutowireTrait;

    const DEV = 'theme:dev';

    public function __construct(
        // @todo Can we avoid the autowire attribute here?
        #[Autowire(service: 'keyvalue')]
        protected KeyValueFactoryInterface $keyValueFactory,
        protected ConfigFactoryInterface $configFactory
    ) {
        parent::__construct();
    }

    /**
     * Toggle Twig development and cache aggregation settings.
     *
     * When enabled:
     * - Disables render cache, dynamic page cache, and page cache.
     * - Enables Twig debug mode (e.g., `dump()` function, template suggestions).
     * - Disables Twig cache (templates always recompiled).
     * - Disables CSS and JS aggregation.
     *
     * When disabled, restores default performance-oriented settings.
     *
     * Clears all Drupal caches to apply changes immediately.
     */
    #[CLI\Command(name: self::DEV, aliases: ['thdev'])]
    #[CLI\Version(version: '13.6')]
    #[CLI\Argument(name: 'mode', description: '"on" or "off"', suggestedValues: ['on', 'off'])]
    #[CLI\Usage(name: 'drush theme:dev on', description: 'Disables CSS/JS aggregation and enables Twig debug settings.')]
    #[CLI\Usage(name: 'drush theme:dev off', description: 'Enables CSS/JS aggregation and disables Twig debug settings.')]
    public function toggleDevMode(string $mode): void
    {
        $devMode = match ($mode) {
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

        $this->logger()->success(sprintf(
            'Developer mode %s: CSS/JS aggregation %s, Twig debug settings %s.',
            $devMode ? 'enabled' : 'disabled',
            $devMode ? 'disabled' : 'enabled',
            $devMode ? 'enabled' : 'disabled'
        ));
    }
}
