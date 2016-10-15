<?php
namespace Drush\CommandFiles\core;

use Consolidation\AnnotatedCommand\CommandData;

class DrupliconCommands {
  protected $printed = false;

  /**
   * Print druplicon as post-command output.
   *
   * @hook post-command *
   * @option druplicon Shows the druplicon as glorious ASCII art.
   * @todo hidden is not yet part of annotated-command project. It is recognized by Drush's annotation_adapter.inc
   * @hidden-option druplicon
   */
  public function druplicon($result, CommandData $commandData) {
    // If one command does a drush_invoke to another command,
    // then this hook will be called multiple times. Only print
    // once.  (n.b. If drush_invoke_process passes along the
    // --druplicon option, then we will still get mulitple output)
    if ($this->printed) {
      return;
    }
    $this->printed = true;
    $annotationData = $commandData->annotationData();
    $commandName = $annotationData['command'];
    // For some reason, Drush help uses drush_invoke_process to call helpsingle
    if ($commandName == 'helpsingle') {
      return;
    }
    if ($commandData->input()->getOption('druplicon')) {
      drush_log(dt('Displaying Druplicon for "!command" command.', array('!command' => $commandName)));
      $misc_dir = DRUSH_BASE_PATH . '/misc';
      if (drush_get_context('DRUSH_NOCOLOR')) {
        $content = file_get_contents($misc_dir . '/druplicon-no_color.txt');
      }
      else {
        $content = file_get_contents($misc_dir . '/druplicon-color.txt');
      }
      // @todo: `$commandData->output->writeln($content)` after $output hooked up to backend invoke
      drush_print($content);
    }
  }
}
