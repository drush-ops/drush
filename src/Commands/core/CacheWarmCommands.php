<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drupal\Core\PreWarm\CachePreWarmerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;

final class CacheWarmCommands extends DrushCommands
{
    const WARM = 'cache:warm';

    public function __construct(
        private CachePreWarmerInterface $warmer,
    ) {
        parent::__construct();
    }

    public static function create(ContainerInterface $container)
    {
        if ($container->has('cache_prewarmer')) {
            return new self($container->get('cache_prewarmer'));
        }
        // Do nothing. Command never gets added to the Application.
    }

    /**
     * Pre-warm all caches.
     */
    #[CLI\Command(name: self::WARM, aliases: ['warm', 'cache-warm'])]
    #[CLI\Version(version: '13.5')]
    public function warm(): void
    {
        $this->warmer->preWarmAllCaches();
        $this->logger()->success(dt('Warmed caches.'));
    }
}
