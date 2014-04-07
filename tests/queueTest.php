<?php

namespace Unish;

/**
 * @group commands
 */
class QueueCase extends CommandUnishTestCase {

  function testQueue() {
    if (UNISH_DRUPAL_MAJOR_VERSION == 6) {
      $this->markTestSkipped("Queue API not available in Drupal 6.");
    }

    if (UNISH_DRUPAL_MAJOR_VERSION == 7) {
      $expected = 'aggregator_feeds,%items,SystemQueue';
    }
    else {
      $expected = 'aggregator_feeds,%items,Drupal\Core\Queue\DatabaseQueue';
    }

    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    // Enable aggregator since it declares a queue.
    $this->drush('pm-enable', array('aggregator'), $options);

    $this->drush('queue-list', array(), $options);
    $output = $this->getOutput();
    $this->assertContains('aggregator_feeds', $output, 'Queue list shows the declared queue.');

    $this->drush('php-script', array('queue_script-D' . UNISH_DRUPAL_MAJOR_VERSION), $options + array('script-path' => dirname(__FILE__) . '/resources'));
    $this->drush('queue-list', array(), $options + array('pipe' => TRUE));
    $output = trim($this->getOutput());
    $parts = explode(",", $output);
    $this->assertEquals(str_replace('%items', 1, $expected), $output, 'Item was successfully added to the queue.');
    $output = $this->getOutput();

    $this->drush('queue-run', array('aggregator_feeds'), $options);
    $this->drush('queue-list', array(), $options + array('pipe' => TRUE));
    $output = trim($this->getOutput());
    $parts = explode(",", $output);
    $this->assertEquals(str_replace('%items', 0, $expected), $output, 'Queue item processed.');
  }
}
