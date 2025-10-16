<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\UnstructuredListData;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Filesystem\Filesystem;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class SiteCommands extends DrushCommands
{
    use AutowireTrait;

    const SET = 'site:set';
    const ALIAS = 'site:alias';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Set a site alias that will persist for the current session.
     *
     * Stores the site alias being used in the current session in a temporary
     * file.
     */
    #[CLI\Command(name: self::SET, aliases: ['use', 'site-set'])]
    #[CLI\Argument(name: 'site', description: 'Site specification to use, or <info>-</info> for previous site. Omit this argument to unset.')]
    #[CLI\Usage(name: 'drush site:set @dev', description: 'Set the current session to use the @dev alias.')]
    #[CLI\Usage(name: 'drush site:set user@server/path/to/drupal#sitename', description: 'Set the current session to use a remote site via site specification.')]
    #[CLI\Usage(name: 'drush site:set /path/to/drupal#sitename', description: 'Set the current session to use a local site via site specification.')]
    #[CLI\Usage(name: 'drush site:set -', description: 'Go back to the previously-set site (like `cd -`).')]
    #[CLI\Usage(name: 'drush site:set', description: 'Without an argument, any existing site becomes unset.')]
    #[CLI\HandleRemoteCommands]
    #[CLI\ValidatePhpExtensions(['posix'])]
    #[CLI\Topics(topics: [DocsCommands::ALIASES])]
    public function siteSet(string $site = '@none'): void
    {
        $filename = $this->getConfig()->get('runtime.site-file-current');
        if ($filename) {
            $last_site_filename = $this->getConfig()->get('runtime.site-file-previous');
            if ($site === '-') {
                if (file_exists($last_site_filename)) {
                    $site = file_get_contents($last_site_filename);
                } else {
                    $site = '@none';
                }
            }
            if ($site == '@self') {
                // TODO: Add a method of SiteAliasManager to find a local
                // alias by directory / by env.cwd.
                //     $path = drush_cwd();
                //     $site_record = drush_sitealias_lookup_alias_by_path($path, true);
                $site_record = []; // This should be returned as an SiteAlias, not an array.
                if (isset($site_record['#name'])) {
                    $site = '@' . $site_record['#name']; // $site_record->name();
                } else {
                    $site = '@none';
                }
                // Using 'site:set @self' is quiet if there is no change.
                $current = is_file($filename) ? trim(file_get_contents($filename)) : "@none";
                if ($current === $site) {
                    return;
                }
            }
            // Alias record lookup exists.
            $aliasRecord = $this->siteAliasManager->get($site);
            if ($aliasRecord) {
                if (file_exists($filename)) {
                    @unlink($last_site_filename);
                    @rename($filename, $last_site_filename);
                }
                $success_message = dt('Site set to @site', ['@site' => $site]);
                $fs = new Filesystem();
                if ($site == '@none' || $site == '') {
                    $fs->remove($filename);
                    $this->logger()->success(dt('Site unset.'));
                } else {
                    $fs->mkdir(dirname($filename));
                    if (file_put_contents($filename, $site)) {
                        $this->logger()->success($success_message);
                        $this->logger()->info(dt('Site information stored in @file', ['@file' => $filename]));
                    }
                }
            } else {
                throw new \Exception(dt('Could not find a site definition for @site.', ['@site' => $site]));
            }
        }
    }

    /**
     * Show site alias details, or a list of available site aliases.
     */
    #[CLI\Command(name: self::ALIAS, aliases: ['sa'])]
    #[CLI\Argument(name: 'site', description: 'Site alias or site specification.')]
    #[CLI\FilterDefaultField(field: 'id')]
    #[CLI\Usage(name: 'drush site:alias', description: 'List all alias records known to drush.')]
    #[CLI\Usage(name: 'drush site:alias @dev', description: 'Print an alias record for the alias <info>dev</info>.')]
    #[CLI\Topics(topics: [DocsCommands::ALIASES])]
    public function siteAlias($site = null, array $options = ['format' => 'yaml']): ?UnstructuredListData
    {
        // First check to see if the user provided a specification that matches
        // multiple sites.
        $aliasList = $this->siteAliasManager->getMultiple($site);
        if (is_array($aliasList) && $aliasList !== []) {
            return new UnstructuredListData($this->siteAliasExportList($aliasList, $options));
        }

        // Next check for a specific alias or a site specification.
        $aliasRecord = $this->siteAliasManager->get($site);
        if ($aliasRecord !== false) {
            return new UnstructuredListData([$aliasRecord->name() => $aliasRecord->export()]);
        }

        if ($site) {
            throw new \Exception('Site alias not found.');
        } else {
            $this->logger()->success('No site aliases found.');
            return null;
        }
    }

    protected function siteAliasExportList(array $aliasList, $options): array
    {
        return array_map(
            function ($aliasRecord) {
                return $aliasRecord->export();
            },
            $aliasList
        );
    }
}
