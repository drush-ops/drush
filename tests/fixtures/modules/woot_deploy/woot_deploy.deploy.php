<?php

declare(strict_types=1);

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and hook_post_update_NAME
 * functions. See https://www.drush.org/latest/deploycommand/#authoring-update-functions
 * for a detailed comparison.
 */

/**
 * Deploy hook in module containing _deploy.
 */
function woot_deploy_deploy_function()
{
    return 'This is the update message from ' . __FUNCTION__;
}
