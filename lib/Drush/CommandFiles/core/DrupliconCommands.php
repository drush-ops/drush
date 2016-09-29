<?php
namespace Drush\CommandFiles\core;

class DrupliconCommands {

  /**
   * Print druplicon as post-command output.
   *
   * @hook post-command *
   * @option $druplicon Shows the druplicon as glorious ASCII art.
   */
  public function druplicon($result, $argsAndOptions, $annotationData) {
    if ($argsAndOptions['options']['druplicon']) {
      $misc_dir = DRUSH_BASE_PATH . '/misc';
      if (drush_get_context('DRUSH_NOCOLOR')) {
        $content = file_get_contents($misc_dir . '/druplicon-no_color.txt');
      }
      else {
        $content = file_get_contents($misc_dir . '/druplicon-color.txt');
      }
      drush_print($content);
    }
  }
}
