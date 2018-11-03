<?php

namespace Drupal\Tests\rest\Kernel\Views;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewTestData;

/**
 * @coversDefaultClass \Drupal\rest\Plugin\views\style\Serializer
 * @group views
 */
class StyleSerializerKernelTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_serializer_display_entity'];

  /**
   * {@inheritdoc}
   */
  public static $modules = ['rest_test_views', 'serialization', 'rest'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    ViewTestData::createTestViews(get_class($this), ['rest_test_views']);
  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDepenencies() {
    /** @var \Drupal\views\Entity\View $view */
    $view = View::load('test_serializer_display_entity');
    $display = &$view->getDisplay('rest_export_1');

    $display['display_options']['defaults']['style'] = FALSE;
    $display['display_options']['style']['type'] = 'serializer';
    $display['display_options']['style']['options']['formats'] = ['json', 'xml'];
    $view->save();

    $view->calculateDependencies();
    $this->assertEquals(['module' => ['rest', 'serialization', 'user']], $view->getDependencies());

    \Drupal::service('module_installer')->install(['hal']);

    $view = View::load('test_serializer_display_entity');
    $display = &$view->getDisplay('rest_export_1');
    $display['display_options']['style']['options']['formats'] = ['json', 'xml', 'hal_json'];
    $view->save();

    $view->calculateDependencies();
    $this->assertEquals(['module' => ['hal', 'rest', 'serialization', 'user']], $view->getDependencies());
  }

}
