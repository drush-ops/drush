<?php

namespace Drupal\Tests\node\Kernel\Plugin\migrate\source\d6;

use Drupal\Tests\migrate\Kernel\MigrateSqlSourceTestBase;

/**
 * Tests D6 node source plugin with 'node_type' configuration.
 *
 * @covers \Drupal\node\Plugin\migrate\source\d6\Node
 *
 * @group node
 */
class NodeByNodeTypeTest extends MigrateSqlSourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'user', 'migrate_drupal'];

  /**
   * {@inheritdoc}
   */
  public function providerSource() {
    $tests = [];

    // The source data.
    $tests[0]['source_data']['node'] = [
      [
        'nid' => 1,
        'vid' => 1,
        'type' => 'page',
        'language' => 'en',
        'title' => 'node title 1',
        'uid' => 1,
        'status' => 1,
        'created' => 1279051598,
        'changed' => 1279051598,
        'comment' => 2,
        'promote' => 1,
        'moderate' => 0,
        'sticky' => 0,
        'tnid' => 1,
        'translate' => 0,
      ],
      [
        'nid' => 2,
        'vid' => 2,
        'type' => 'page',
        'language' => 'en',
        'title' => 'node title 2',
        'uid' => 1,
        'status' => 1,
        'created' => 1279290908,
        'changed' => 1279308993,
        'comment' => 0,
        'promote' => 1,
        'moderate' => 0,
        'sticky' => 0,
        'tnid' => 2,
        'translate' => 0,
      ],
      // Add another row with an article node and make sure it is not migrated.
      [
        'nid' => 5,
        'vid' => 5,
        'type' => 'article',
        'language' => 'en',
        'title' => 'node title 5',
        'uid' => 1,
        'status' => 1,
        'created' => 1279290908,
        'changed' => 1279308993,
        'comment' => 0,
        'promote' => 1,
        'moderate' => 0,
        'sticky' => 0,
        'tnid' => 0,
        'translate' => 0,
      ],
    ];

    $tests[0]['source_data']['node_revisions'] = [
      [
        'nid' => 1,
        'vid' => 1,
        'title' => 'node title 1',
        'uid' => 2,
        'timestamp' => 1279051598,
        'body' => 'body for node 1',
        'teaser' => 'teaser for node 1',
        'format' => 1,
        'log' => 'log message 1',
      ],
      [
        'nid' => 2,
        'vid' => 2,
        'title' => 'node title 2',
        'uid' => 2,
        'timestamp' => 1279290908,
        'body' => 'body for node 2',
        'teaser' => 'teaser for node 2',
        'format' => 1,
        'log' => 'log message 2',
      ],
      // Add another row with an article node and make sure it is not migrated.
      [
        'nid' => 5,
        'vid' => 5,
        'title' => 'node title 5',
        'uid' => 2,
        'timestamp' => 1279290908,
        'body' => 'body for node 5',
        'teaser' => 'body for node 5',
        'format' => 1,
        'log' => 'log message 3',
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      [
        // Node fields.
        'nid' => 1,
        'vid' => 1,
        'type' => 'page',
        'language' => 'en',
        'title' => 'node title 1',
        'node_uid' => 1,
        'revision_uid' => 2,
        'status' => 1,
        'timestamp' => 1279051598,
        'created' => 1279051598,
        'changed' => 1279051598,
        'comment' => 2,
        'promote' => 1,
        'moderate' => 0,
        'sticky' => 0,
        'tnid' => 1,
        'translate' => 0,
        // Node revision fields.
        'body' => 'body for node 1',
        'teaser' => 'teaser for node 1',
        'format' => 1,
        'log' => 'log message 1',
      ],
      [
        // Node fields.
        'nid' => 2,
        'vid' => 2,
        'type' => 'page',
        'language' => 'en',
        'title' => 'node title 2',
        'node_uid' => 1,
        'revision_uid' => 2,
        'status' => 1,
        'timestamp' => 1279290908,
        'created' => 1279290908,
        'changed' => 1279308993,
        'comment' => 0,
        'promote' => 1,
        'moderate' => 0,
        'sticky' => 0,
        'tnid' => 2,
        'translate' => 0,
        // Node revision fields.
        'body' => 'body for node 2',
        'teaser' => 'teaser for node 2',
        'format' => 1,
        'log' => 'log message 2',
      ],
    ];

    // Do an automatic count.
    $tests[0]['expected_count'] = NULL;

    // Set up source plugin configuration.
    $tests[0]['configuration'] = [
      'node_type' => 'page',
    ];

    return $tests;
  }

}
