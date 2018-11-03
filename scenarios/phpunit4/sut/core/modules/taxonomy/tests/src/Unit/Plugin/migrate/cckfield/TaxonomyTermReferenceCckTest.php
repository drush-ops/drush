<?php

namespace Drupal\Tests\taxonomy\Unit\Plugin\migrate\cckfield;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\taxonomy\Plugin\migrate\cckfield\TaxonomyTermReference;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\taxonomy\Plugin\migrate\cckfield\TaxonomyTermReference
 * @group taxonomy
 * @group legacy
 */
class TaxonomyTermReferenceCckTest extends UnitTestCase {

  /**
   * @var \Drupal\migrate_drupal\Plugin\MigrateCckFieldInterface
   */
  protected $plugin;

  /**
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->plugin = new TaxonomyTermReference([], 'taxonomy', []);

    $migration = $this->prophesize(MigrationInterface::class);

    // The plugin's processCckFieldValues() method will call
    // setProcessOfProperty() and return nothing. So, in order to examine the
    // process pipeline created by the plugin, we need to ensure that
    // getProcess() always returns the last input to setProcessOfProperty().
    $migration->setProcessOfProperty(Argument::type('string'), Argument::type('array'))
      ->will(function ($arguments) use ($migration) {
        $migration->getProcess()->willReturn($arguments[1]);
      });

    $this->migration = $migration->reveal();
  }

  public function testProcessCckFieldValues() {
    $this->testDefineValueProcessPipeline('processCckFieldValues');
  }

  /**
   * @covers ::defineValueProcessPipeline
   */
  public function testDefineValueProcessPipeline($method = 'defineValueProcessPipeline') {
    $this->plugin->$method($this->migration, 'somefieldname', []);

    $expected = [
      'plugin' => 'sub_process',
      'source' => 'somefieldname',
      'process' => [
        'target_id' => 'tid',
      ],
    ];
    $this->assertSame($expected, $this->migration->getProcess());
  }

}
