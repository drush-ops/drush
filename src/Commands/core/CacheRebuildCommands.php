<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Composer\Autoload\ClassLoader;
use Consolidation\SiteAlias\SiteAliasManager;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drush\Drush;
use Psr\Container\ContainerInterface as DrushContainer;

final class CacheRebuildCommands extends DrushCommands
{
    const REBUILD = 'cache:rebuild';

    public function __construct(
        private BootstrapManager $bootstrapManager,
        private ClassLoader $autoloader
    ) {
        parent::__construct();
    }

    public static function createEarly(DrushContainer $drush_container): self
    {
        $commandHandler = new static(
            $drush_container->get('bootstrap.manager'),
            $drush_container->get('loader')
        );

        return $commandHandler;
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

        // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
        drupal_rebuild($this->autoloader, $request);
        $this->logger()->success(dt('Cache rebuild complete.'));
    }
}
