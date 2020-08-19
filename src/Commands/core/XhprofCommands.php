<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Drush;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Commands\DrushCommands;

/**
 * Class XhprofCommands
 * @package Drush\Commands\core
 *
 * Supports profiling Drush commands using either XHProf or Tideways XHProf.
 *
 * Note that XHProf is compatible with PHP 5.6 and PHP 7+, you could also use
 * the Tideways XHProf fork. The Tideways XHProf extension recently underwent a
 * major refactor; Drush is only compatible with the newer version.
 *
 * @see https://pecl.php.net/package/xhprof
 * @see https://tideways.com/profiler/blog/releasing-new-tideways-xhprof-extension
 */
class XhprofCommands extends DrushCommands
{

    const XH_PROFILE_MEMORY = false;
    const XH_PROFILE_CPU = false;
    const XH_PROFILE_BUILTINS = true;


  // @todo Add a command for launching the built-in web server pointing to the HTML site of xhprof.
  // @todo Write a topic explaining how to use this.

    /**
     * @hook option *
     *
     * @option xh-link URL to your XHProf report site.
     */
    public function optionsetXhProf($options = ['xh-link' => self::REQ])
    {
    }

    /**
     * Enable profiling via XHProf
     *
     * @hook post-command *
     */
    public function xhprofPost($result, CommandData $commandData)
    {
        if (self::xhprofIsEnabled()) {
            $namespace = 'Drush';
            $run_id = self::xhprofFinishRun($namespace);
            $url = $this->getConfig()->get('xh.link') . '/index.php?run=' . urlencode($run_id) . '&source=' . urlencode($namespace);
            $this->logger()->notice(dt('XHProf run saved. View report at !url', ['!url' => $url]));
        }
    }

    /**
     * Enable profiling via XHProf
     *
     * @hook init *
     */
    public function xhprofInitialize(InputInterface $input, AnnotationData $annotationData)
    {
        if (self::xhprofIsEnabled()) {
            $config = $this->getConfig()->get('xh');
            $flags = self::xhprofFlags($config);
            self::xhprofEnable($flags);
        }
    }

    public static function xhprofIsEnabled()
    {
        if (Drush::config()->get('xh.link')) {
            if (!extension_loaded('xhprof') && !extension_loaded('tideways_xhprof')) {
                if (extension_loaded('tideways')) {
                    throw new \Exception(dt('You are using an older incompatible version of the tideways extension. Please upgrade to the new tideways_xhprof extension.'));
                } else {
                    throw new \Exception(dt('You must enable the xhprof or tideways_xhprof PHP extensions in your CLI PHP in order to profile.'));
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Determines flags.
     */
    public static function xhprofFlags(array $config)
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
        if (!(isset($config['profile-builtins']) ? $config['profile-builtins']: self::XH_PROFILE_BUILTINS)) {
            $flags |= $map['no-builtins'];
        }
        if (isset($config['profile-cpu']) ? $config['profile-cpu'] : self::XH_PROFILE_CPU) {
            $flags |= $map['cpu'];
        }
        if (isset($config['profile-memory']) ? $config['profile-memory'] : self::XH_PROFILE_MEMORY) {
            $flags |= $map['memory'];
        }
        return $flags;
    }

    /**
     * Enable profiling.
     */
    public static function xhprofEnable($flags)
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
            $dir = $this->getConfig()->tmp();
            $run_id = uniqid();
            file_put_contents($dir . DIRECTORY_SEPARATOR . $run_id . '.' . $namespace . '.xhprof', serialize($data));
            return $run_id;
        } else {
            $xhprof_data = \xhprof_disable();
            $xhprof_runs = new \XHProfRuns_Default();
            return $xhprof_runs->save_run($xhprof_data, $namespace);
        }
    }
}
