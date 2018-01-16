<?php

/**
 * Successful post-update.
 */
function woot_post_update_a()
{
    // Note that this is called 'a' so that it will run first. The post-updates
    // are executed in alphabetical order.
    return t('This is the update message from woot_post_update_a');
}

/**
 * Failing post-update.
 */
function woot_post_update_failing()
{
    throw new \Exception('This is the exception message thrown in woot_post_update_failing');
}

/**
 * Install the Devel module.
 */
function woot_post_update_install_devel()
{
    \Drupal::service('module_installer')->install(['devel']);
}
