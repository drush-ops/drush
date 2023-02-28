<?php

namespace Unish;

use Drush\Drupal\Commands\core\QueueCommands;
use Symfony\Component\Filesystem\Path;

/**
 * @group commands
 */
class QueueTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

  /**
   * Tests the RequeueException.
   */
    public function testRequeueException()
    {
        $sites = $this->setUpDrupal(1, true);

        // Copy the 'woot' module over to the Drupal site we just set up.
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules'));

        // Enable woot module, which contains a queue worker that throws a
        // RequeueException.
        $this->drush('pm:install', ['woot'], [], null, null, self::EXIT_SUCCESS);

        // Add an item to the queue.
        $this->drush('php:script', ['requeue_script'], ['script-path' => __DIR__ . '/resources']);

        // Check that the queue exists and it has one item in it.
        $this->drush(QueueCommands::LIST, [], ['format' => 'json']);
        $output = $this->getOutputFromJSON('woot_requeue_exception');
        $this->assertStringContainsString(1, $output['items'], 'Item was successfully added to the queue.');
        // Process the queue.
        $this->drush(QueueCommands::RUN, ['woot_requeue_exception']);

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
        $this->drush(QueueCommands::LIST, [], ['format' => 'json']);
        $output = $this->getOutputFromJSON('woot_requeue_exception');
        $this->assertStringContainsString(0, $output['items'], 'Queue item processed after being requeued.');
    }

  /**
   * Tests that CustomExceptions do not hold up the queue. Also queue:run, queue:list, queue:delete
   */
    public function testCustomExceptionAndCommands()
    {
        $this->setUpDrupal(1, true);

        // Copy the 'woot' module over to the Drupal site we just set up.
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules'));

        // Enable woot module, which contains a queue worker that throws a
        // custom exception.
        $this->drush('pm:install', ['woot'], [], null, null, self::EXIT_SUCCESS);

        // Add a couple of items to the queue.
        $this->drush('php:script', ['queue_custom_exception_script'], ['script-path' => __DIR__ . '/resources']);

        // Check that the queue exists and it has two items in it.
        $this->drush(QueueCommands::LIST, [], ['format' => 'json']);
        $output = $this->getOutputFromJSON('woot_custom_exception');
        $this->assertStringContainsString(2, $output['items'], 'Items were successfully added to the queue.');

        // Process the queue.
        $this->drush(QueueCommands::RUN, ['woot_custom_exception']);

        // Check that the item was processed after being requeued once.
        // Here is the detailed workflow of what the above command did. Note
        // there are two items in the queue when we begin.
        // 1. Drush calls drush queue-run woot_custom_exception.
        // 2. Drush claims the item. The worker sets a state variable (see below)
        // and throws a CustomException.
        // 3. Drush catches the exception and skips the item.
        // 4. Drush claims the second item.
        // 5. The worker finds the state variable, so it does not throw the
        // CustomException this time (see below).
        // 6. Drush removes the second item from the queue.
        // 7. Command finishes. The queue is left with the first item, which was
        // skipped.
        $this->drush(QueueCommands::LIST, [], ['format' => 'json']);
        $output = $this->getOutputFromJSON('woot_custom_exception');
        $this->assertStringContainsString(1, $output['items'], 'Last queue item processed after first threw custom exception.');

        $this->drush(QueueCommands::DELETE, ['woot_custom_exception']);
        $this->drush(QueueCommands::LIST, [], ['format' => 'json']);
        $output = $this->getOutputFromJSON('woot_custom_exception');
        $this->assertEquals(0, $output['items'], 'Queue was successfully deleted.');
    }
}
