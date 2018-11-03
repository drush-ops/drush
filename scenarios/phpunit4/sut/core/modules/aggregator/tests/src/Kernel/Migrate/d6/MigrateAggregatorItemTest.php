<?php

namespace Drupal\Tests\aggregator\Kernel\Migrate\d6;

use Drupal\aggregator\Entity\Item;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests migration of aggregator items.
 *
 * @group migrate_drupal_6
 */
class MigrateAggregatorItemTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['aggregator'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('aggregator_feed');
    $this->installEntitySchema('aggregator_item');
    $this->executeMigrations(['d6_aggregator_feed', 'd6_aggregator_item']);
  }

  /**
   * Test Drupal 6 aggregator item migration to Drupal 8.
   */
  public function testAggregatorItem() {
    /** @var \Drupal\aggregator\Entity\Item $item */
    $item = Item::load(1);
    $this->assertIdentical('1', $item->id());
    $this->assertIdentical('5', $item->getFeedId());
    $this->assertIdentical('This (three) weeks in Drupal Core - January 10th 2014', $item->label());
    $this->assertIdentical('larowlan', $item->getAuthor());
    $this->assertIdentical("<h2 id='new'>What's new with Drupal 8?</h2>", $item->getDescription());
    $this->assertIdentical('https://groups.drupal.org/node/395218', $item->getLink());
    $this->assertIdentical('1389297196', $item->getPostedTime());
    $this->assertIdentical('en', $item->language()->getId());
    $this->assertIdentical('395218 at https://groups.drupal.org', $item->getGuid());

  }

}
