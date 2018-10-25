<?php
namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

class ConfigPullCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Export and transfer config from one environment to another.
     *
     * @command config:pull
     * @param string $source A site-alias or the name of a subdirectory within /sites whose config you want to copy from.
     * @param string $destination A site-alias or the name of a subdirectory within /sites whose config you want to replace.
     * @param array $options
     * @throws \Exception
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
     */
    public function pull($source, $destination, $options = ['safe' => false, 'label' => 'sync', 'runner' => null, 'format' => 'null'])
    {
        $global_options = Drush::redispatchOptions()  + ['strict' => 0];
        $sourceRecord = $this->siteAliasManager()->get($source);

        $export_options = [
            // Use the standard backup directory on Destination.
            'destination' => true,
            'yes' => null,
        ];
        $this->logger()->notice(dt('Starting to export configuration on :destination.', [':destination' => $destination]));
        $process = Drush::siteProcess($sourceRecord, 'config-export', [], $global_options + $export_options);
        $process->mustRun();
        // Trailing slash assures that we transfer files and not the containing dir.
        $export_path = Drush::simulate() ? '/simulated/path' : $process->getOutput() . '/';

        if (strpos($destination, ':') === false) {
            $destination .= ':%config-' . $options['label'];
        }
        $destinationHostPath = HostPath::create($this->siteAliasManager(), $destination);

        if (!$runner = $options['runner']) {
            $destinationRecord = $destinationHostPath->getAliasRecord();
            $runner = $sourceRecord->isRemote() && $destinationRecord->isRemote() ? $destinationRecord : $this->siteAliasManager()->getSelf();
        }
        $this->logger()
          ->notice(dt('Starting to rsync configuration files from !source to !dest.', [
              '!source' => "$source:$export_path",
              '!dest' => $destinationHostPath->getOriginal(),
          ])
        );
        $args = ["$source:$export_path", $destinationHostPath->getOriginal()];
        $options_double_dash = [
            '--remove-source-files',
            '--delete',
            '--exclude=.htaccess',
        ];
        $process = Drush::siteProcess($runner, 'core-rsync', $args, ['yes' => true], $options_double_dash);
        $process->mustRun();
        drush_backend_set_result($destinationHostPath->getOriginal());
        return $destinationHostPath->getOriginal();
    }

    /**
     * @hook validate config-pull
     */
    public function validateConfigPull(CommandData $commandData)
    {
        if ($commandData->input()->getOption('safe')) {
            $destinationRecord = $this->siteAliasManager()->get($commandData->input()->getArgument('destination'));
            $process = Drush::siteProcess($destinationRecord, 'core-execute', ['git diff --quiet'], ['escape' => 0]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception('There are uncommitted changes in your git working copy.');
            }
        }
    }
}
