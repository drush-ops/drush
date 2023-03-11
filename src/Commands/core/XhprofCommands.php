<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Config\DrushConfig;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Commands\DrushCommands;

/**
 * Supports profiling Drush commands using either XHProf or Tideways XHProf.
 *
 * Note that XHProf is compatible with PHP 5.6 and PHP 7+, you could also use
 * the Tideways XHProf fork. The Tideways XHProf extension recently underwent a
 * major refactor; Drush is only compatible with the newer version.
 *
 * @see https://pecl.php.net/package/xhprof
 * @see https://tideways.com/profiler/blog/releasing-new-tideways-xhprof-extension
 */
final class XhprofCommands extends DrushCommands
{
    const XH_PROFILE_MEMORY = false;
    const XH_PROFILE_CPU = false;
    const XH_PROFILE_BUILTINS = true;


  // @todo Add a command for launching the built-in web server pointing to the HTML site of xhprof.
  // @todo Write a topic explaining how to use this.

    #[CLI\Hook(type: HookManager::OPTION_HOOK, target: '*')]
    #[CLI\Option(name: 'xh-link', description: 'URL to your XHProf report site.')]
    public function optionsetXhProf($options = ['xh-link' => self::REQ]): void
    {
    }

    /**
     * Finish profiling and emit a link.
     */
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: '*')]
    public function xhprofPost($result, CommandData $commandData): void
    {
        $config = $this->getConfig();
        if (self::xhprofIsEnabled($config)) {
            $namespace = 'Drush';
            $run_id = self::xhprofFinishRun($namespace);
            $url = $config->get('xh.link') . '/index.php?run=' . urlencode($run_id) . '&source=' . urlencode($namespace);
            $this->logger()->notice(dt('XHProf run saved. View report at !url', ['!url' => $url]));
        }
    }

    /**
     * Enable profiling via XHProf
     */
    #[CLI\Hook(type: HookManager::INITIALIZE, target: '*')]
    public function xhprofInitialize(InputInterface $input, AnnotationData $annotationData): void
    {
        $config = $this->getConfig();
        if (self::xhprofIsEnabled($config)) {
            $flags = self::xhprofFlags($config);
            self::xhprofEnable($flags);
        }
    }

    /**
     * Determines if any profiler could be enabled.
     *
     * @param DrushConfig $config
     *
     *   TRUE when xh.link configured, FALSE otherwise.
     *
     * @throws \Exception
     *   When no available profiler extension enabled.
     */
    public static function xhprofIsEnabled(DrushConfig $config): bool
    {
        if (!$config->get('xh.link')) {
            return false;
        }
        if (!extension_loaded('xhprof') && !extension_loaded('tideways_xhprof')) {
            if (extension_loaded('tideways')) {
                throw new \Exception(dt('You are using an older incompatible version of the tideways extension. Please upgrade to the new tideways_xhprof extension.'));
            } else {
                throw new \Exception(dt('You must enable the xhprof or tideways_xhprof PHP extensions in your CLI PHP in order to profile.'));
            }
        }
        return true;
    }

    /**
     * Determines flags.
     */
    public static function xhprofFlags(DrushConfig $config): int
    {
        if (extension_loaded('tideways_xhprof')) {
            $map = [
                'no-builtins' => TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS,
                'cpu' => TIDEWAYS_XHPROF_FLAGS_CPU,
                'memory' => TIDEWAYS_XHPROF_FLAGS_MEMORY,
            ];
        } else {
            $map = [
                'no-builtins' => XHPROF_FLAGS_NO_BUILTINS,
                'cpu' => XHPROF_FLAGS_CPU,
                'memory' => XHPROF_FLAGS_MEMORY,
            ];
        }

        $flags = 0;
        if (!$config->get('xh.profile-builtins', !self::XH_PROFILE_BUILTINS)) {
            $flags |= $map['no-builtins'];
        }
        if ($config->get('xh.profile-cpu', self::XH_PROFILE_CPU)) {
            $flags |= $map['cpu'];
        }
        if ($config->get('xh.profile-memory', self::XH_PROFILE_MEMORY)) {
            $flags |= $map['memory'];
        }
        return $flags;
    }

    /**
     * Enable profiling.
     */
    public static function xhprofEnable($flags): void
    {
        if (extension_loaded('tideways_xhprof')) {
            \tideways_xhprof_enable($flags);
        } else {
            \xhprof_enable($flags);
        }
    }

    /**
     * Disable profiling and save results.
     */
    public function xhprofFinishRun($namespace)
    {
        if (extension_loaded('tideways_xhprof')) {
            $data = \tideways_xhprof_disable();
        } else {
            $data = \xhprof_disable();
            if (class_exists('\XHProfRuns_Default')) {
                $xhprof_runs = new \XHProfRuns_Default($this->getConfig()->get('xh.path'));
                return $xhprof_runs->save_run($data, $namespace);
            }
        }
        $config = $this->getConfig();
        $dir = $config->get('xh.path', $config->tmp());
        $run_id = uniqid();
        file_put_contents($dir . DIRECTORY_SEPARATOR . $run_id . '.' . $namespace . '.xhprof', serialize($data));
        return $run_id;
    }
}
