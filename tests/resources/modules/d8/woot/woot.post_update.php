<?php

/**
 * Failing post-update.
 */
function woot_post_update_failing()
{
    throw new \Exception('post update error');
}

/**
 * Install the Devel module.
 */
function woot_post_update_install_devel()
{
    \Drupal::service('module_installer')->install(['devel']);
}
