<?php

/**
 * @file
 * Creates the woot_requeue_exception queue and adds an item to it.
 *
 * @see WootRequeueException
 */

$queue_factory = \Drupal::service('queue');
$queue = $queue_factory->get('woot_requeue_exception', TRUE);
$queue->createItem(['foo' => 'bar']);
