<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drush\Drush;

/**
 * cache:rebuild must not use a create() method or else it will not stop at SITE bootstrap.
 */
final class CacheRebuildCommands extends DrushCommands
{
    const REBUILD = 'cache:rebuild';

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
        $bootstrapManager = Drush::bootstrapManager();
        $root  = $bootstrapManager->getRoot();
        require_once DRUSH_DRUPAL_CORE . '/includes/utility.inc';

        $request = $bootstrapManager->bootstrap()->getRequest();
        DrupalKernel::bootEnvironment();

        $site_path = DrupalKernel::findSitePath($request);
        $autoloader = Drush::getContainer()->get('loader');
        Settings::initialize($root, $site_path, $autoloader);

        // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
        drupal_rebuild($autoloader, $request);
        $this->logger()->success(dt('Cache rebuild complete.'));
    }
}
