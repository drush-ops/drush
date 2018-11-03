<?php

namespace Drupal\Tests\filter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests filter settings.
 *
 * @group filter
 */
class FilterSettingsTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['filter'];

  /**
   * Tests explicit and implicit default settings for filters.
   */
  public function testFilterDefaults() {
    $filter_info = $this->container->get('plugin.manager.filter')->getDefinitions();

    // Create text format using filter default settings.
    $filter_defaults_format = FilterFormat::create([
      'format' => 'filter_defaults',
      'name' => 'Filter defaults',
    ]);
    $filter_defaults_format->save();

    // Verify that default weights defined in hook_filter_info() were applied.
    $saved_settings = [];
    foreach ($filter_defaults_format->filters() as $name => $filter) {
      $expected_weight = $filter_info[$name]['weight'];
      $this->assertEqual($filter->weight, $expected_weight, format_string('@name filter weight %saved equals %default', [
        '@name' => $name,
        '%saved' => $filter->weight,
        '%default' => $expected_weight,
      ]));
      $saved_settings[$name]['weight'] = $expected_weight;
    }

    // Re-save the text format.
    $filter_defaults_format->save();
    // Reload it from scratch.
    filter_formats_reset();

    // Verify that saved filter settings have not been changed.
    foreach ($filter_defaults_format->filters() as $name => $filter) {
      $this->assertEqual($filter->weight, $saved_settings[$name]['weight'], format_string('@name filter weight %saved equals %previous', [
        '@name' => $name,
        '%saved' => $filter->weight,
        '%previous' => $saved_settings[$name]['weight'],
      ]));
    }
  }

}
