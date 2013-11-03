<?php

$values = array(
  'title' => 'test',
  'url' => 'http://drupal.org/project/issues/rss/drupal?categories=All',
  'refresh' => 3600,
);
$feed = entity_create('aggregator_feed', $values);
$feed->save();

// Let cron call QueueInterface::createItem() for us.
aggregator_cron();
