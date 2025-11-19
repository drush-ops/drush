<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: self::NAME,
    description: 'Set a site alias that will persist for the current session.',
    aliases: ['use', 'site-set']
)]
#[CLI\HandleRemoteCommands]
// #[CLI\ValidatePhpExtensions(['posix'])]
#[CLI\HelpLinks(links: [HelpLinks::Aliases])]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class SiteSetCommand extends Command
{
    use AutowireTrait;

    const string NAME = 'site:set';

    public function __construct(
        private readonly DrushConfig $drushConfig,
        protected readonly LoggerInterface $logger,
        private readonly SiteAliasManagerInterface $siteAliasManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('site', InputArgument::OPTIONAL, 'Site specification to use, or <info>-</info> for previous site. Omit this argument to unset.', '@none')
            ->addUsage('site:set @dev')
            ->addUsage('site:set user@server/path/to/drupal#sitename')
            ->addUsage('site:set /path/to/drupal#sitename')
            ->addUsage('site:set -')
            ->addUsage('site:set');

        $this->setHelp('Stores the site alias being used in the current session in a temporary file. Use @dev to set current session to use the @dev alias. Use - to go back to the previously-set site (like `cd -`). Without an argument, any existing site becomes unset.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $site = $input->getArgument('site');

        $filename = $this->drushConfig->get('runtime.site-file-current');
        if ($filename) {
            $last_site_filename = $this->drushConfig->get('runtime.site-file-previous');
            if ($site === '-') {
                $site = file_exists($last_site_filename) ? file_get_contents($last_site_filename) : '@none';
            }
            if ($site == '@self') {
                // TODO: Add a method of SiteAliasManager to find a local
                // alias by directory / by env.cwd.
                //     $path = drush_cwd();
                //     $site_record = drush_sitealias_lookup_alias_by_path($path, true);
                $site_record = []; // This should be returned as an SiteAlias, not an array.
                // if (isset($site_record['#name'])) {
                //    $site = '@' . $site_record['#name']; // $site_record->name();
                // } else {
                    $site = '@none';
                //}
                // Using 'site:set @self' is quiet if there is no change.
                $current = is_file($filename) ? trim(file_get_contents($filename)) : "@none";
                if ($current === $site) {
                    return self::SUCCESS;
                }
            }
            // Alias record lookup exists.
            $aliasRecord = $this->siteAliasManager->get($site);
            if ($aliasRecord) {
                if (file_exists($filename)) {
                    @unlink($last_site_filename);
                    @rename($filename, $last_site_filename);
                }
                $fs = new Filesystem();
                if ($site == '@none' || $site == '') {
                    $fs->remove($filename);
                    $io->success('Site unset.');
                } else {
                    $fs->mkdir(dirname($filename));
                    if (file_put_contents($filename, $site)) {
                        $io->success(sprintf('Site set to %s', $site));
                        $this->logger->info('Site information stored in {filename}', ['filename' => $filename]);
                    }
                }
            } else {
                throw new \Exception(sprintf('Could not find a site definition for %s.', $site));
            }
        }

        return self::SUCCESS;
    }
}
