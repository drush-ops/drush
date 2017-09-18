<?php

use Drupal\aggregator\Entity\Feed;

// Create a new feed.
$feed = Feed::create(array(
  'title' => 'test',
  'url' => 'http://drupal.org/project/issues/rss/drupal?categories=All',
  'uid' => 2,
  'refresh' => 3600,
));
$feed->save();

// Let cron call QueueInterface::createItem() for us.
aggregator_cron();
