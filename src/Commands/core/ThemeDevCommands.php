<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ThemeDevCommands extends DrushCommands
{
    const DEV = 'theme:dev';

    public function __construct(
        protected KeyValueFactoryInterface $keyValueFactory,
        protected ConfigFactoryInterface $configFactory
    ) {
    }

    public static function create(ContainerInterface $container): self
    {
        return new self(
            $container->get('keyvalue'),
            $container->get('config.factory')
        );
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
        if (!in_array($mode, ['on', 'off'])) {
            throw new \InvalidArgumentException("Invalid mode. Use 'on' or 'off'.");
        }

        $devMode = $mode === 'on';

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
            $mode === 'on' ? 'enabled' : 'disabled',
            $devMode ? 'disabled' : 'enabled',
            $devMode ? 'enabled' : 'disabled'
        ));
    }
}
