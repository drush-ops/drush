<?php

/**
 * @file
 * Contains \Drupal\Tests\migrate\Unit\destination\PerComponentEntityFormDisplayTest.
 */

namespace Drupal\Tests\migrate\Unit\destination;

use Drupal\migrate\Plugin\migrate\destination\PerComponentEntityFormDisplay;
use Drupal\migrate\Row;
use Drupal\Tests\migrate\Unit\MigrateTestCase;

/**
 * Tests the entity display destination plugin.
 *
 * @group migrate
 */
class PerComponentEntityFormDisplayTest extends MigrateTestCase {

  /**
   * Tests the entity display import method.
   */
  public function testImport() {
    $values = [
      'entity_type' => 'entity_type_test',
      'bundle' => 'bundle_test',
      'form_mode' => 'form_mode_test',
      'field_name' => 'field_name_test',
      'options' => ['test setting'],
    ];
    $row = new Row();
    foreach ($values as $key => $value) {
      $row->setDestinationProperty($key, $value);
    }
    $entity = $this->getMockBuilder('Drupal\Core\Entity\Entity\EntityFormDisplay')
      ->disableOriginalConstructor()
      ->getMock();
    $entity->expects($this->once())
      ->method('setComponent')
      ->with('field_name_test', ['test setting'])
      ->will($this->returnSelf());
    $entity->expects($this->once())
      ->method('save')
      ->with();
    $plugin = new TestPerComponentEntityFormDisplay($entity);
    $this->assertSame(['entity_type_test', 'bundle_test', 'form_mode_test', 'field_name_test'], $plugin->import($row));
    $this->assertSame(['entity_type_test', 'bundle_test', 'form_mode_test'], $plugin->getTestValues());
  }

}

class TestPerComponentEntityFormDisplay extends PerComponentEntityFormDisplay {
  const MODE_NAME = 'form_mode';
  protected $testValues;

  public function __construct($entity) {
    $this->entity = $entity;
  }

  protected function getEntity($entity_type, $bundle, $form_mode) {
    $this->testValues = func_get_args();
    return $this->entity;
  }

  public function getTestValues() {
    return $this->testValues;
  }

}
