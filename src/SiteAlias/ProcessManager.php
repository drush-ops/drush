<?php
namespace Drush\SiteAlias;

use Consolidation\SiteProcess\ProcessManager as ConsolidationProcessManager;

use Psr\Log\LoggerInterface;
use Consolidation\SiteAlias\AliasRecord;
use Consolidation\SiteProcess\Factory\TransportFactoryInterface;
use Symfony\Component\Process\Process;
use Drush\Drush;
use Drush\Style\DrushStyle;
use Consolidation\SiteProcess\ProcessBase;
use Consolidation\SiteProcess\SiteProcess;

/**
 * The Drush ProcessManager adds a few Drush-specific service methods.
 */
class ProcessManager extends ConsolidationProcessManager
{
    /**
     * Run a Drush command on a site alias (or @self).
     *
     * @param AliasRecord $siteAlias
     * @param string $command
     * @param array $args
     * @param array $options
     * @param array $options_double_dash
     * @return SiteProcess
     */
    public function drush(AliasRecord $siteAlias, $command, $args = [], $options = [], $options_double_dash = [])
    {
        array_unshift($args, $command);
        return $this->drushSiteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * drushSiteProcess should be avoided in favor of the drush method above.
     * drushSiteProcess exists specifically for use by the RedispatchHook,
     * which does not have specific knowledge about which argument is the command.
     *
     * @param AliasRecord $siteAlias
     * @param array $args
     * @param array $options
     * @param array $options_double_dash
     * @return ProcessBase
     */
    public function drushSiteProcess(AliasRecord $siteAlias, $args = [], $options = [], $options_double_dash = [])
    {
        // TODO: If local, we should try to find vendor/bin/drush at the local root
        // and use that if it exists, falling back to Drush::drushScript() if it does not.
        $defaultDrushScript = !$siteAlias->isLocal() ? 'drush' : Drush::drushScript();

        // Fill in the root and URI from the site alias, if the caller
        // did not already provide them in $options.
        if ($siteAlias->has('uri')) {
            $options += [ 'uri' => $siteAlias->uri(), ];
        }
        if ($siteAlias->hasRoot()) {
            $options += [ 'root' => $siteAlias->root(), ];
        }
        array_unshift($args, $siteAlias->get('paths.drush-script', $defaultDrushScript));

        return $this->siteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * @inheritdoc
     *
     * Use ProcessManager::drush() instead of this method when calling Drush.
     */
    public function siteProcess(AliasRecord $siteAlias, $args = [], $options = [], $optionsPassedAsArgs = [])
    {
        $process = parent::siteProcess($siteAlias, $args, $options, $optionsPassedAsArgs);
        return $this->configureProcess($process);
    }

    /**
     * Run a bash fragment locally.
     *
     * The timeout parameter on this method doesn't work. It exists for compatibility with parent.
     * Call this method to get a Process and then call setters as needed.
     *
     * @param string|array   $commandline The command line to run
     * @param string|null    $cwd         The working directory or null to use the working dir of the current PHP process
     * @param array|null     $env         The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null     $input       The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout     The timeout in seconds or null to disable
     * @param array          $options     An array of options for proc_open
     *
     * @return ProcessBase
     *   A wrapper around Symfony Process.
     */
    public function process($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60, array $options = null)
    {
        $process = new ProcessBase($commandline, $cwd, $env, $input, $timeout, $options);
        return $this->configureProcess($process);
    }

    /**
     * configureProcess sets up a process object so that it is ready to use.
     */
    protected static function configureProcess(ProcessBase $process)
    {
        $process->setSimulated(Drush::simulate());
        $process->setVerbose(Drush::verbose());
        $process->inheritEnvironmentVariables();
        $process->setLogger(Drush::logger());
        $process->setRealtimeOutput(new DrushStyle(Drush::input(), Drush::output()));
        $process->setTimeout(Drush::getTimeout());
        return $process;
    }
}
