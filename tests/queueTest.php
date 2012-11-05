<?php

/*
 * @file
 *   Tests for queue commands.
 *
 * @group commands
 */
class QueueCase extends Drush_CommandTestCase {

  function testQueue() {
    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    // Enable aggregator since it declares a queue.
    $this->drush('en', array('aggregator'), $options);

    $this->drush('queue-list', array(), $options);
    $output = $this->getOutput();
    $this->assertContains('aggregator_feeds', $output, 'Queue list shows the declared queue.');

    $this->drush('php-script', array('queue_script'), $options + array('script-path' => dirname(__FILE__) . '/resources'));
    $this->drush('queue-list', array(), $options + array('pipe' => TRUE));
    $output = trim($this->getOutput());
    $parts = explode(",", $output);
    $this->assertEquals('aggregator_feeds,1,SystemQueue', $output, 'Item was successfully added to the queue.');
    $output = $this->getOutput();

    $this->drush('queue-run', array('aggregator_feeds'), $options);
    $this->drush('queue-list', array(), $options + array('pipe' => TRUE));
    $output = trim($this->getOutput());
    $parts = explode(",", $output);
    $this->assertEquals('aggregator_feeds,0,SystemQueue', $output, 'Queue item processed.');
  }
}
