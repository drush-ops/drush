<?php

/**
 * Failing post-update.
 */
function woot_post_update_failing()
{
    throw new \Exception('post update error');
}
