<?php

namespace Drupal\Tests\tracker\Kernel\Plugin\migrate\source\d7;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D7 tracker node source plugin.
 *
 * @covers Drupal\tracker\Plugin\migrate\source\d7\TrackerNode
 *
 * @group tracker
 */
class TrackerNodeTest extends MigrateSqlSourceTestBase {

  /**
  * {@inheritdoc}
  */
  public static $modules = ['tracker', 'migrate_drupal'];

  /**
  * {@inheritdoc}
  */
  public function providerSource() {
    $tests = [];

    // The source data.
    $tests[0]['database']['tracker_node'] = [
      [
        'nid' => '2',
        'published' => '1',
        'changed' => '1421727536',
      ],
    ];

    // The expected results are identical to the source data.
    $tests[0]['expected_results'] = $tests[0]['database']['tracker_node'];

    return $tests;
  }

}
