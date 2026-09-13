<?php

declare(strict_types=1);

/**
 * @file
 * Creates the woot_suspend_queue_exception queue and adds a couple items to it.
 *
 * @see WootSuspendQueueException
 */

$queue_factory = \Drupal::service('queue');
$queue = $queue_factory->get('woot_suspend_queue_exception', true);
$queue->createItem(['foo' => 'bar']);
$queue->createItem(['baz' => 'qux']);
