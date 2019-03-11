<?php
namespace Drush\SiteAlias;

use Consolidation\SiteProcess\ProcessManager as ConsolidationProcessManager;

use Consolidation\SiteProcess\Util\Escape;
use Psr\Log\LoggerInterface;
use Consolidation\SiteAlias\SiteAliasInterface;
use Consolidation\SiteProcess\Factory\TransportFactoryInterface;
use Symfony\Component\Process\Process;
use Drush\Drush;
use Drush\Style\DrushStyle;
use Consolidation\SiteProcess\ProcessBase;
use Consolidation\SiteProcess\SiteProcess;
use Webmozart\PathUtil\Path;

/**
 * The Drush ProcessManager adds a few Drush-specific service methods.
 */
class ProcessManager extends ConsolidationProcessManager
{
    /**
     * Run a Drush command on a site alias (or @self).
     *
     * @param SiteAliasInterface $siteAlias
     * @param string $command
     * @param array $args
     * @param array $options
     * @param array $options_double_dash
     * @return SiteProcess
     */
    public function drush(SiteAliasInterface $siteAlias, $command, $args = [], $options = [], $options_double_dash = [])
    {
        array_unshift($args, $command);
        return $this->drushSiteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * drushSiteProcess should be avoided in favor of the drush method above.
     * drushSiteProcess exists specifically for use by the RedispatchHook,
     * which does not have specific knowledge about which argument is the command.
     *
     * @param SiteAliasInterface $siteAlias
     * @param array $args
     * @param array $options
     * @param array $options_double_dash
     * @return ProcessBase
     */
    public function drushSiteProcess(SiteAliasInterface $siteAlias, $args = [], $options = [], $options_double_dash = [])
    {
        // Fill in the root and URI from the site alias, if the caller
        // did not already provide them in $options.
        if ($siteAlias->has('uri')) {
            $options += [ 'uri' => $siteAlias->uri(), ];
        }
        if ($siteAlias->hasRoot()) {
            $options += [ 'root' => $siteAlias->root(), ];
        }

        // The executable is always 'drush' (at some path or another)
        array_unshift($args, $this->drushScript($siteAlias));

        return $this->siteProcess($siteAlias, $args, $options, $options_double_dash);
    }

    /**
     * Determine the path to Drush to use
     */
    public function drushScript(SiteAliasInterface $siteAlias)
    {
        $defaultDrushScript = 'drush';

        // If the site alias has 'paths.drush-script', always use that.
        if ($siteAlias->has('paths.drush-script')) {
            return $siteAlias->get('paths.drush-script');
        }

        // If the provided site alias is for a remote site / container et. al.,
        // then use the 'drush' in the $PATH.
        if ($this->hasTransport($siteAlias)) {
            return $defaultDrushScript;
        }

        // If the target is a local Drupal site that has a vendor/bin/drush,
        // then use that.
        if ($siteAlias->hasRoot()) {
            $localDrushScript = Path::join($siteAlias->root(), 'vendor/bin/drush');
            if (file_exists($localDrushScript)) {
                return $localDrushScript;
            }
        }

        // Otherwise, use the path to the version of Drush that is running
        // right now (if available).
        return $this->getConfig()->get('runtime.drush-script', $defaultDrushScript);
    }

    /**
     * @inheritdoc
     *
     * Use Drush::drush() or ProcessManager::drush() instead of this method
     * when calling Drush.
     */
    public function siteProcess(SiteAliasInterface $siteAlias, $args = [], $options = [], $optionsPassedAsArgs = [])
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
     * @param array          $commandline The command line to run with arguments as separate items in an array
     * @param string|null    $cwd         The working directory or null to use the working dir of the current PHP process
     * @param array|null     $env         The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null     $input       The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout     The timeout in seconds or null to disable
     *
     * @return ProcessBase
     *   A wrapper around Symfony Process.
     */
    public function process($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        $process = parent::process($commandline, $cwd, $env, $input, $timeout);
        return $this->configureProcess($process);
    }

    /**
     * Create a Process instance from a commandline string.
     * @param string $command The commandline string to run
     * @param string|null $cwd     The working directory or null to use the working dir of the current PHP process
     * @param array|null $env     The environment variables or null to use the same environment as the current PHP process
     * @param mixed|null $input   The input as stream resource, scalar or \Traversable, or null for no input
     * @param int|float|null $timeout The timeout in seconds or null to disable
     * @return Process
     */
    public function shell($command, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        $process = parent::shell($command, $cwd, $env, $input, $timeout);
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
