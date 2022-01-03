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
    $this->assertStringContainsString('aggregator_feeds', $output, 'Queue list shows the declared queue.');

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

  /**
   * Tests the RequeueException.
   */
  public function testRequeueException() {
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $this->markTestSkipped("RequeueException only available in Drupal 8.");
    }

    $sites = $this->setUpDrupal(1, TRUE);
    $options = array(
      'yes' => NULL,
      'root' => $this->webroot(),
      'uri' => key($sites),
    );

    // Copy the 'woot' module over to the Drupal site we just set up.
    $this->setupModulesForTests($this->webroot());

    // Enable woot module, which contains a queue worker that throws a
    // RequeueException.
    $this->drush('pm-enable', array('woot'), $options, NULL, NULL, self::EXIT_SUCCESS);

    // Add an item to the queue.
    $this->drush('php-script', array('requeue_script'), $options + array('script-path' => dirname(__FILE__) . '/resources'));

    // Check that the queue exists and it has one item in it.
    $expected = 'woot_requeue_exception,%items,Drupal\Core\Queue\DatabaseQueue';
    $this->drush('queue-list', array(), $options + array('pipe' => TRUE));
    $output = trim($this->getOutput());
    $this->assertEquals(str_replace('%items', 1, $expected), $output, 'Item was successfully added to the queue.');

    // Process the queue.
    $this->drush('queue-run', array('woot_requeue_exception'), $options);

    // Check that the item was processed after being requeued once.
    // Here is the detailed workflow of what the above command did.
    // 1. Drush calls drush queue-run woot_requeue_exception.
    // 2. Drush claims the item. The worker sets a state variable (see below)
    // and throws the RequeueException.
    // 3. Drush catches the exception and puts it back in the queue.
    // 4. Drush claims the next item, which is the one that we just requeued.
    // 5. The worker finds the state variable, so it does not throw the
    // RequeueException this time (see below).
    // 6. Drush removes the item from the queue.
    // 7. Command finishes. The queue is empty.
    $this->drush('queue-list', array(), $options + array('pipe' => TRUE));
    $output = trim($this->getOutput());
    $this->assertEquals(str_replace('%items', 0, $expected), $output, 'Queue item processed after being requeued.');
  }

  /**
   * Copies the woot module into Drupal.
   *
   * @param string $root
   *   The path to the root directory of Drupal.
   */
  public function setupModulesForTests($root) {
    $wootMajor = UNISH_DRUPAL_MAJOR_VERSION == '9' ? '8' : UNISH_DRUPAL_MAJOR_VERSION;
    $wootModule = __DIR__ . '/resources/modules/d' . $wootMajor . '/woot';
    $modulesDir = "$root/sites/all/modules";
    $this->mkdir($modulesDir);
    \symlink($wootModule, "$modulesDir/woot");
  }

}
