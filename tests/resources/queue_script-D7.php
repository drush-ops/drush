<?php

// Create a new feed.
aggregator_save_feed(array(
  'title' => 'test',
  'url' => 'https://www.drupal.org/project/issues/rss/goofy?categories=All',
  'refresh' => 3600,
  'block' => 5,
));

// Let cron call DrupalQueue::createItem() for us.
aggregator_cron();
