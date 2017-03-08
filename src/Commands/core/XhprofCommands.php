<?php

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Input\InputInterface;
use Drush\Commands\DrushCommands;

class XhprofCommands extends DrushCommands {

  // @todo Add a command for launching the built-in web server pointing to the
  // HTML site of xhprof.
  // @todo write a topic explaining how to use this.

  /**
   * Enable profiling via XHProf
   *
   * @hook post-command *
   * @option xh-link URL to your XHProf report site.
   * @option xh-profile-builtins Profile built-in PHP functions (defaults to TRUE).
   * @option xh-profile-cpu Profile CPU (defaults to FALSE).
   * @option xh-profile-memory Profile Memory (defaults to FALSE).
   * @hidden-option xh-link,xh-profile-cpu-xh-profile-builtins,xh-profile-memory
   */
  function xhprofPost($result, CommandData $commandData) {
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
  function xhprofInitialize(InputInterface $input, AnnotationData $annotationData) {
    if (self::xhprofIsEnabled($input)) {
      xhprof_enable(xh_flags());
    }
  }

  public static function xhprofIsEnabled(InputInterface $input) {
    if ($input->getOption('xh-link')) {
      if (!extension_loaded('xhprof') && !extension_loaded('tideways')) {
        throw new \Exception(dt('You must enable the xhprof or tideways PHP extensions in your CLI PHP in order to profile.'));
      }
      return TRUE;
    }
  }

  /**
   * Determines flags.
   */
  public static function xhprofFlags(CommandData $commandData) {
    $flags = 0;
    if (!$commandData->input()->getOption('xh-profile-builtins') ?: XH_PROFILE_BUILTINS) {
      $flags |= XHPROF_FLAGS_NO_BUILTINS;
    }
    if ($commandData->input()->getOption('xh-profile-cpu') ?: XH_PROFILE_CPU) {
      $flags |= XHPROF_FLAGS_CPU;
    }
    if ($commandData->input()->getOption('xh-profile-memory') ?: XH_PROFILE_MEMORY) {
      $flags |= XHPROF_FLAGS_MEMORY;
    }
    return $flags;
  }
}
