<?php
$feed = array(
  'title' => 'test',
  'url' => 'http://drupal.org/project/issues/rss/drupal?categories=All',
  'refresh' => 3600,
  'block' => 5,
);

// Create a new feed.
aggregator_save_feed($feed);
// Let cron call DrupalQueue::createItem() for us.
aggregator_cron();
