<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrushLoggerServiceProvider;
use Drush\Runtime\DependencyInjection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CacheRebuildCommands extends DrushCommands
{
    const REBUILD = 'cache:rebuild';

    public function __construct(
        #[Autowire(service: DependencyInjection::BOOTSTRAP_MANAGER)]
        private BootstrapManager $bootstrapManager,
        #[Autowire(service: DependencyInjection::LOADER)]
        private ClassLoader $autoloader
    ) {
        parent::__construct();
    }

    /**
     * Rebuild all caches.
     *
     * This is a copy of core/rebuild.php.
     */
    #[CLI\Command(name: self::REBUILD, aliases: ['cr', 'rebuild', 'cache-rebuild'])]
    #[CLI\Option(name: 'cache-clear', description: 'Set to 0 to suppress normal cache clearing; the caller should then clear if needed.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::SITE)]
    public function rebuild($options = ['cache-clear' => true])
    {
        if (!$options['cache-clear']) {
            $this->logger()->info(dt("Skipping cache-clear operation due to --no-cache-clear option."));
            return true;
        }

        // We no longer clear APC and similar caches as they are useless on CLI.
        // See https://github.com/drush-ops/drush/pull/2450
        $root  = $this->bootstrapManager->getRoot();
        require_once DRUSH_DRUPAL_CORE . '/includes/utility.inc';

        $request = $this->bootstrapManager->bootstrap()->getRequest();
        DrupalKernel::bootEnvironment();

        $site_path = DrupalKernel::findSitePath($request);
        Settings::initialize($root, $site_path, $this->autoloader);

        // Coax \Drupal\Core\DrupalKernel::discoverServiceProviders to add our logger.
        $GLOBALS['conf']['container_service_providers'][] = DrushLoggerServiceProvider::class;

        // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
        drupal_rebuild($this->autoloader, $request);
        $this->logger()->success(dt('Cache rebuild complete.'));
    }
}
