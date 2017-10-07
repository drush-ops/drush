<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Drush;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Commands\DrushCommands;

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
    public function optionsetXhProf()
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
            $xhprof_data = xhprof_disable();
            $xhprof_runs = new \XHProfRuns_Default();
            $run_id =  $xhprof_runs->save_run($xhprof_data, $namespace);
            $namespace = 'Drush';
            $url = Drush::config()->get('xh.link') . '/index.php?run=' . urlencode($run_id) . '&source=' . urlencode($namespace);
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
        if (self::xhprofIsEnabled($input)) {
            xhprof_enable(self::xhprofFlags());
        }
    }

    public static function xhprofIsEnabled()
    {
        if (Drush::config()->get('xh.link')) {
            if (!extension_loaded('xhprof') && !extension_loaded('tideways')) {
                throw new \Exception(dt('You must enable the xhprof or tideways PHP extensions in your CLI PHP in order to profile.'));
            }
            return true;
        }
    }

    /**
     * Determines flags.
     */
    public static function xhprofFlags()
    {
        $flags = 0;
        if (Drush::config()->get('xh.profile-builtins', self::XH_PROFILE_BUILTINS)) {
            $flags |= XHPROF_FLAGS_NO_BUILTINS;
        }
        if (Drush::config()->get('xh.profile-cpu', self::XH_PROFILE_CPU)) {
            $flags |= XHPROF_FLAGS_CPU;
        }
        if (Drush::config()->get('xh.profile-memory', self::XH_PROFILE_MEMORY)) {
            $flags |= XHPROF_FLAGS_MEMORY;
        }
        return $flags;
    }
}
