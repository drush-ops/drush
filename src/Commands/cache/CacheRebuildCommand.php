<?php

declare(strict_types=1);

namespace Drush\Commands\cache;

use Composer\Autoload\ClassLoader;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Site\Settings;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\DrushLoggerServiceProvider;
use Drush\Drupal\Migrate\MigrateRunnerServiceProvider;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Rebuild all caches',
    aliases: ['cr', 'rebuild', 'cache-rebuild'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::SITE)]
class CacheRebuildCommand extends Command
{
    use AutowireTrait;

    const NAME = 'cache:rebuild';

    protected function __construct(
        private readonly LoggerInterface $logger,
        private readonly BootstrapManager $bootstrapManager,
        private ClassLoader $autoloader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(name: 'cache-clear', mode: InputOption::VALUE_NEGATABLE, description: 'Use --no-cache-clear to suppress normal cache clearing')
            ->setHelp('This is a copy of core/rebuild.php');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (str_contains(strval($input), 'cache-clear') && !$input->getoption('cache-clear')) {
            $this->logger->info(dt("Skipping cache:rebuild due to --no-cache-clear option."));
            return self::SUCCESS;
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
        // Implement a hook in behalf of 'system' module until #2952291 lands.
        // @see https://www.drupal.org/project/drupal/issues/2952291
        $GLOBALS['conf']['container_service_providers'][] = MigrateRunnerServiceProvider::class;

        // drupal_rebuild() calls drupal_flush_all_caches() itself, so we don't do it manually.
        drupal_rebuild($this->autoloader, $request);
        (new DrushStyle($input, $output))->success('Cache rebuild complete.');
        return Command::SUCCESS;
    }
}
