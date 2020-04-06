<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and hook_post_update_NAME
 * functions. Also, the `drush deploy` command runs those before config:import.
 */

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
    $sandbox['current'] = isset($sandbox['current']) ? ++$sandbox['current'] : 1;
    $sandbox['#finished'] = (int) $sandbox['current'] === 3;
    if ($sandbox['#finished']) {
        return "Finished at {$sandbox['current']}.";
    }
    return "Iteration {$sandbox['current']}.";
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
