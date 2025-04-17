<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Toggle between twig debug and caching aggregation.
 */
final class CachingAggregationCommands extends DrushCommands
{
    const DEV_MODE = 'dev-mode';

    protected KeyValueFactoryInterface $keyValueFactory;
    protected ConfigFactoryInterface $configFactory;

    public function __construct(
        KeyValueFactoryInterface $keyValueFactory,
        ConfigFactoryInterface $configFactory
    ) {
        $this->keyValueFactory = $keyValueFactory;
        $this->configFactory = $configFactory;
    }

    public static function create($container): self
    {
        return new self(
            $container->get('keyvalue'),
            $container->get('config.factory')
        );
    }

    /**
     * Toggle developer mode.
     *
     * @param string $mode
     *   Accepts "on" or "off".
     */
    #[CLI\Command(name: self::DEV_MODE, aliases: ['dev'])]
    #[CLI\Argument(name: 'mode', description: '"on" or "off"')]
    #[CLI\Usage(name: 'drush dev on', description: 'Disables CSS/JS aggregation and enables Twig debugging.')]
    #[CLI\Usage(name: 'drush dev off', description: 'Enables CSS/JS aggregation and disables Twig debugging.')]
    public function toggleDevMode(string $mode): void
    {
        if (!in_array($mode, ['on', 'off'])) {
            $this->logger()->error("Invalid mode. Use 'on' or 'off'.");
            return;
        }

        $dev_mode = $mode === 'on';

        $this->keyValueFactory->get('development_settings')->setMultiple([
            'disable_rendered_output_cache_bins' => $dev_mode,
            'twig_debug' => $dev_mode,
            'twig_cache_disable' => $dev_mode,
        ]);

        $this->configFactory->getEditable('system.performance')
            ->set('css.preprocess', !$dev_mode)
            ->set('js.preprocess', !$dev_mode)
            ->save();

        drupal_flush_all_caches();

        $this->logger()->success(sprintf(
            'Developer mode %s: CSS/JS aggregation %s, Twig debugging %s.',
            $mode === 'on' ? 'enabled' : 'disabled',
            $dev_mode ? 'disabled' : 'enabled',
            $dev_mode ? 'enabled' : 'disabled'
        ));
    }
}
