<?php
namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\HostPath;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

class ConfigPullCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Export and transfer config from one environment to another.
     *
     * @command config:pull
     * @param string $source A site-alias or the name of a subdirectory within /sites whose config you want to copy from.
     * @param string $destination A site-alias or the name of a subdirectory within /sites whose config you want to replace.
     * @option safe Validate that there are no git uncommitted changes before proceeding
     * @option label A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync'
     * @option runner Where to run the rsync command; defaults to the local site. Can also be 'source' or 'destination'
     * @usage drush config:pull @prod @stage
     *   Export config from @prod and transfer to @stage.
     * @usage drush config:pull @prod @self --label=vcs
     *   Export config from @prod and transfer to the 'vcs' config directory of current site.
     * @usage drush config:pull @prod @self:../config/sync
     *   Export config to a custom directory. Relative paths are calculated from Drupal root.
     * @aliases cpull,config-pull
     * @topics docs:aliases,docs:config-exporting
     *
     */
    public function pull($source, $destination, $options = ['safe' => false, 'label' => 'sync', 'runner' => null])
    {
        $global_options = Drush::redispatchOptions()  + ['strict' => 0];

        // @todo If either call is made interactive, we don't get an $return['object'] back.
        $backend_options = ['interactive' => false];
        if (Drush::simulate()) {
            $backend_options['backend-simulate'] = true;
        }

        $export_options = [
            // Use the standard backup directory on Destination.
            'destination' => true,
            'yes' => null,
        ];
        $this->logger()->notice(dt('Starting to export configuration on Target.'));
        $return = drush_invoke_process($source, 'config-export', [], $global_options + $export_options, $backend_options);
        if ($return['error_status']) {
              throw new \Exception(dt('Config-export failed.'));
        } else {
              // Trailing slash assures that we transfer files and not the containing dir.
              $export_path = $return['object'] . '/';
        }

        $rsync_options = [
            '--remove-source-files',
            '--delete',
            '--exclude=.htaccess',
        ];
        if (strpos($destination, ':') === false) {
            $destination .= ':%config-' . $options['label'];
        }
        $destinationHostPath = HostPath::create($this->siteAliasManager(), $destination);

        if (!$runner = $options['runner']) {
            $sourceRecord = $this->siteAliasManager()->get($source);
            $destinationRecord = $destinationHostPath->getAliasRecord();
            $runner = $sourceRecord->isRemote() && $destinationRecord->isRemote() ? $destinationRecord : '@self';
        }
        $this->logger()
          ->notice(dt('Starting to rsync configuration files from !source to !dest.', [
          '!source' => $source,
          '!dest' => $destinationHostPath->getOriginal(),
          ]));
        // This comment applies similarly to sql-sync's use of core-rsync.
        // Since core-rsync is a strict-handling command and drush_invoke_process() puts options at end, we can't send along cli options to rsync.
        // Alternatively, add options like ssh.options to a site alias (usually on the machine that initiates the sql-sync).
        $return = drush_invoke_process($runner, 'core-rsync', array_merge([
            "$source:$export_path",
            $destinationHostPath->getOriginal(),
            '--'
        ], $rsync_options), ['yes' => true], $backend_options);
        if ($return['error_status']) {
            throw new \Exception(dt('Config-pull rsync failed.'));
        }

        drush_backend_set_result($return['object']);
    }

    /**
     * @hook validate config-pull
     */
    public function validateConfigPull(CommandData $commandData)
    {
        if ($commandData->input()->getOption('safe')) {
            $return = drush_invoke_process($commandData->input()
            ->getArgument('destination'), 'core-execute', ['git diff --quiet'], ['escape' => 0]);
            if ($return['error_status']) {
                  throw new \Exception('There are uncommitted changes in your git working copy.');
            }
        }
    }
}
