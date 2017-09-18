<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Commands\DrushCommands;

class XhprofCommands extends DrushCommands
{

    const XH_PROFILE_MEMORY = false;
    const XH_PROFILE_CPU = false;
    const XH_PROFILE_BUILTINS = true;


  // @todo Add a command for launching the built-in web server pointing to the
  // HTML site of xhprof.
  // @todo write a topic explaining how to use this.

    /**
     * @hook option *
     *
     * @option xh-link URL to your XHProf report site.
     * @option xh-profile-builtins Profile built-in PHP functions (defaults to TRUE).
     * @option xh-profile-cpu Profile CPU (defaults to FALSE).
     * @option xh-profile-memory Profile Memory (defaults to FALSE).
     * @hidden-options xh-link,xh-profile-cpu,xh-profile-builtins,xh-profile-memory
     */
    public function optionsetXhProf($options = ['xh-profile-cpu' => null, 'xh-profile-builtins' => null, 'xh-profile-memory' => null])
    {
    }

    /**
     * Enable profiling via XHProf
     *
     * @hook post-command *
     */
    public function xhprofPost($result, CommandData $commandData)
    {
        if (self::xhprofIsEnabled($commandData->input())) {
            $namespace = 'Drush';
            $xhprof_data = xhprof_disable();
            $xhprof_runs = new \XHProfRuns_Default();
            $run_id =  $xhprof_runs->save_run($xhprof_data, $namespace);
            $namespace = 'Drush';
            $url = $commandData->input()->getOption('xh-link') . '/index.php?run=' . urlencode($run_id) . '&source=' . urlencode($namespace);
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
            xhprof_enable(self::xhprofFlags($input->getOptions()));
        }
    }

    public static function xhprofIsEnabled(InputInterface $input)
    {
        if ($input->getOption('xh-link')) {
            if (!extension_loaded('xhprof') && !extension_loaded('tideways')) {
                throw new \Exception(dt('You must enable the xhprof or tideways PHP extensions in your CLI PHP in order to profile.'));
            }
            return true;
        }
    }

    /**
     * Determines flags.
     */
    public static function xhprofFlags($options)
    {
        $flags = 0;
        if (!(!is_null($options['xh-profile-builtins']) ? $options['xh-profile-builtins'] : self::XH_PROFILE_BUILTINS)) {
            $flags |= XHPROF_FLAGS_NO_BUILTINS;
        }
        if (!is_null($options['xh-profile-cpu']) ? $options['xh-profile-cpu'] : self::XH_PROFILE_CPU) {
            $flags |= XHPROF_FLAGS_CPU;
        }
        if (!is_null($options['xh-profile-memory']) ? $options['xh-profile-memory'] : self::XH_PROFILE_MEMORY) {
            $flags |= XHPROF_FLAGS_MEMORY;
        }
        return $flags;
    }
}
