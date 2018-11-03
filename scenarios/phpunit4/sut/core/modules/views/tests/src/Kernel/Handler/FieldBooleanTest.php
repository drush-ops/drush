<?php

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the core Drupal\views\Plugin\views\field\Boolean handler.
 *
 * @group views
 */
class FieldBooleanTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  public function dataSet() {
    // Use default dataset but remove the age from john and paul
    $data = parent::dataSet();
    $data[0]['age'] = 0;
    $data[3]['age'] = 0;
    return $data;
  }

  public function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data']['age']['field']['id'] = 'boolean';
    return $data;
  }

  public function testFieldBoolean() {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('fields', [
      'age' => [
        'id' => 'age',
        'table' => 'views_test_data',
        'field' => 'age',
        'relationship' => 'none',
      ],
    ]);

    $this->executeView($view);

    // This is john, which has no age, there are no custom formats defined, yet.
    $this->assertEqual(t('No'), $view->field['age']->advancedRender($view->result[0]));
    $this->assertEqual(t('Yes'), $view->field['age']->advancedRender($view->result[1]));

    // Reverse the output.
    $view->field['age']->options['not'] = TRUE;
    $this->assertEqual(t('Yes'), $view->field['age']->advancedRender($view->result[0]));
    $this->assertEqual(t('No'), $view->field['age']->advancedRender($view->result[1]));

    unset($view->field['age']->options['not']);

    // Use another output format.
    $view->field['age']->options['type'] = 'true-false';
    $this->assertEqual(t('False'), $view->field['age']->advancedRender($view->result[0]));
    $this->assertEqual(t('True'), $view->field['age']->advancedRender($view->result[1]));

    // test awesome unicode.
    $view->field['age']->options['type'] = 'unicode-yes-no';
    $this->assertEqual('✖', $view->field['age']->advancedRender($view->result[0]));
    $this->assertEqual('✔', $view->field['age']->advancedRender($view->result[1]));

    // Set a custom output format.
    $view->field['age']->formats['test'] = [t('Test-True'), t('Test-False')];
    $view->field['age']->options['type'] = 'test';
    $this->assertEqual(t('Test-False'), $view->field['age']->advancedRender($view->result[0]));
    $this->assertEqual(t('Test-True'), $view->field['age']->advancedRender($view->result[1]));
  }

}
