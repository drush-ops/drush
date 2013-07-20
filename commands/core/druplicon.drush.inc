<?php
/**
 * @file
 * Show Druplicon ASCII art at the end of any request which has --druplicon on it.
 */

/**
 * Implements hook_drush_help_alter().
 */
function druplicon_drush_help_alter(&$command) {
  if ($command['command'] == 'global-options' && $command['#brief'] === FALSE) {
    $command['options']['druplicon'] = 'Shows the druplicon as glorious ASCII art.';
  }
}

/**
 * Implements hook_drush_exit().
 */
function druplicon_drush_exit() {
  if (drush_get_option('druplicon')) {
    $misc_dir = realpath(dirname(__FILE__)) . '/../../misc';
    if (drush_get_context('DRUSH_NOCOLOR')) {
      $content = file_get_contents($misc_dir . '/druplicon-no_color.txt');
    }
    else {
      $content = file_get_contents($misc_dir . '/druplicon-color.txt');
    }
    drush_print($content);
  }
}
