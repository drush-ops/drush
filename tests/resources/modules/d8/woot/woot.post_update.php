<?php

/**
 * Failing post-update.
 */
function woot_post_update_failing() {
  throw new \Exception('Exception in woot_post_update_failing()');
}
