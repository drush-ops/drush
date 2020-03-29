<?php

/**
 * Successful deploy hook.
 */
function woot_deploy_a()
{
    // Note that this is called 'a' so that it will run first. The deploy hooks
    // are executed in alphabetical order.
    return t('This is the update message from woot_deploy_a');
}

/**
 * Successful batched deploy hook.
 */
function woot_deploy_batch(array &$sandbox)
{
    module_load_install('woot');
    return woot_update_8105($sandbox);
}

/**
 * Failing deploy hook.
 */
function woot_deploy_failing()
{
    if (\Drupal::state()->get('woot_deploy_pass', false)) {
        return t('Now woot_deploy_failing is passing');
    }
    throw new \Exception('This is the exception message thrown in woot_deploy_failing');
}
