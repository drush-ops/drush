<?php

namespace Drupal\Tests\user\Functional\Views;

use Drupal\views\Views;

/**
 * Tests the representative node relationship for users.
 *
 * @group user
 */
class RelationshipRepresentativeNodeTest extends UserTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_groupwise_user'];

  /**
   * Tests the relationship.
   */
  public function testRelationship() {
    $view = Views::getView('test_groupwise_user');
    $this->executeView($view);
    $map = ['node_field_data_users_field_data_nid' => 'nid', 'uid' => 'uid'];
    $expected_result = [
      [
        'uid' => $this->users[1]->id(),
        'nid' => $this->nodes[1]->id(),
      ],
      [
        'uid' => $this->users[0]->id(),
        'nid' => $this->nodes[0]->id(),
      ],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $map);
  }

}
