<?php

namespace Drupal\KernelTests\Core\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\EventSubscriber\ReplicaDatabaseIgnoreSubscriber;
use Drupal\Core\DrupalKernel;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Tests that ReplicaDatabaseIgnoreSubscriber functions correctly.
 *
 * @group system
 */
class IgnoreReplicaSubscriberTest extends KernelTestBase {

  /**
   * Tests \Drupal\Core\EventSubscriber\ReplicaDatabaseIgnoreSubscriber::checkReplicaServer().
   */
  public function testSystemInitIgnoresSecondaries() {
    // Clone the master credentials to a replica connection.
    // Note this will result in two independent connection objects that happen
    // to point to the same place.
    $connection_info = Database::getConnectionInfo('default');
    Database::addConnectionInfo('default', 'replica', $connection_info['default']);

    db_ignore_replica();
    $class_loader = require $this->root . '/autoload.php';
    $kernel = new DrupalKernel('testing', $class_loader, FALSE);
    $event = new GetResponseEvent($kernel, Request::create('http://example.com'), HttpKernelInterface::MASTER_REQUEST);
    $subscriber = new ReplicaDatabaseIgnoreSubscriber();
    $subscriber->checkReplicaServer($event);

    $db1 = Database::getConnection('default', 'default');
    $db2 = Database::getConnection('replica', 'default');

    $this->assertSame($db1, $db2, 'System Init ignores secondaries when requested.');
  }

}
