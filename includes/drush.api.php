<?php

/**
 * Executes a deploy function which is intended to update data, like entities,
 * after config is imported during a deployment.
 *
 * These are a higher level alternative to hook_update_n and hook_deploy_NAME
 * functions and have to be placed in a MODULE.deploy.php file.
 *
 * NAME can be arbitrary machine names. In contrast to hook_update_N() the
 * alphanumeric naming of functions in the file is the only thing which ensures
 * the execution order of those functions. If update order is mandatory,
 * you should add numerical prefix to NAME or make it completely numerical.
 *
 * Drupal also ensures to not execute the same hook_deploy_NAME() function
 * twice.
 *
 * @section sec_bulk Batch updates
 * If running your update all at once could possibly cause PHP to time out, use
 * the $sandbox parameter to indicate that the Batch API should be used for your
 * update. In this case, your update function acts as an implementation of
 * callback_batch_operation(), and $sandbox acts as the batch context
 * parameter. In your function, read the state information from the previous
 * run from $sandbox (or initialize), run a chunk of updates, save the state in
 * $sandbox, and set $sandbox['#finished'] to a value between 0 and 1 to
 * indicate the percent completed, or 1 if it is finished (you need to do this
 * explicitly in each pass).
 *
 * See the @link batch Batch operations topic @endlink for more information on
 * how to use the Batch API.
 *
 * @param array $sandbox
 *   Stores information for batch updates. See above for more information.
 *
 * @return string|null
 *   Optionally, hook_deploy_NAME() hooks may return a translated string
 *   that will be displayed to the user after the update has completed. If no
 *   message is returned, no message will be presented to the user.
 *
 * @throws \Exception
 *   In case of error, update hooks should throw an instance of
 *   \Exception with a meaningful message for the user.
 *
 * @ingroup update_api
 *
 * @see hook_update_N()
 * @see hook_post_update_N()
 */
function hook_deploy_NAME(array &$sandbox): string {
  $node = \Drupal\node\Entity\Node::load(123);
  $node->setTitle('foo');
  $node->save();

  return t('Node %nid saved', ['%nid' => $node->id()]);
}
