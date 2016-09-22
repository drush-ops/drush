<?php
namespace Drush\CommandFiles\core;

class DrupliconCommands {

  /*
   * @todo not working until we modernize drush_get_global_options().
   * @hook alter help
   */
  public function help($command) {
    if ($command['command'] == 'global-options' && $command['#brief'] === FALSE) {
      $command['options']['druplicon'] = [
        'description' => 'Shows the druplicon as glorious ASCII art.',
      ];
    }
  }

  /**
   * Print druplicon as post-command output.
   *
   * @hook post-command
   */
  public function append() {
    if (drush_get_option('druplicon')) {
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