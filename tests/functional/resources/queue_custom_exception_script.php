<?php

/**
 * @file
 * Creates the woot_custom_exception queue and adds a couple items to it.
 *
 * @see WootCustomException
 */

$queue_factory = \Drupal::service('queue');
$queue = $queue_factory->get('woot_custom_exception', true);
$queue->createItem(['foo' => 'bar']);
$queue->createItem(['baz' => 'qux']);
