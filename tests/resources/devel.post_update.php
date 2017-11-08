<?php

/**
 * This is a test of the emergency broadcast system.
 */
function devel_post_update_null_op(&$sandbox = null)
{
    $sandbox['#finished'] = 1;
    return t('Done doing nothing.');
}