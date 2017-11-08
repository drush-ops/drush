<?php

/**
 * A sneaky implementation of hook_module_implements_alter().
 *
 * site:install includes this file in order to not run any cron hooks during install.
 * That's a bad idea.
 *
 * @param $implementations
 * @param $hook
 */
function system_module_implements_alter(&$implementations, $hook) {
  if ($hook == 'cron') {
    $implementations = [];
  }
}
