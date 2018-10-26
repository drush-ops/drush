<?php

/**
 * Wrapping function for invoking the right
 * method responsible for handle a given batch operation.
 * Available for every DevelGeneratePlugin.
 */
function devel_generate_operation(Drupal\devel_generate\DevelGenerateBaseInterface $class, $method, $vars, &$context) {
  return $class->$method($vars, $context);
}

/**
 * Common finish batch function available for
 * every DevelGeneratePlugin.
 */
function devel_generate_batch_finished($success, $results, $operations) {

  if ($success) {
    $message = t('Finished @num elements created successfully.', array('@num' => $results['num']));
  }
  else {
    $message = t('Finished with an error.');
  }
  drupal_set_message($message);
}
