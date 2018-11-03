<?php

namespace Drupal\Tests\content_translation\Kernel;

use Drupal\content_translation\FieldTranslationSynchronizer;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the field synchronization logic.
 *
 * @group content_translation
 */
class ContentTranslationSyncUnitTest extends KernelTestBase {

  /**
   * The synchronizer class to be tested.
   *
   * @var \Drupal\content_translation\FieldTranslationSynchronizer
   */
  protected $synchronizer;

  /**
   * The columns to be synchronized.
   *
   * @var array
   */
  protected $synchronized;

  /**
   * All the field columns.
   *
   * @var array
   */
  protected $columns;

  /**
   * The available language codes.
   *
   * @var array
   */
  protected $langcodes;

  /**
   * The field cardinality.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * The unchanged field values.
   *
   * @var array
   */
  protected $unchangedFieldValues;

  public static $modules = ['language', 'content_translation'];

  protected function setUp() {
    parent::setUp();

    $this->synchronizer = new FieldTranslationSynchronizer($this->container->get('entity.manager'), $this->container->get('plugin.manager.field.field_type'));
    $this->synchronized = ['sync1', 'sync2'];
    $this->columns = array_merge($this->synchronized, ['var1', 'var2']);
    $this->langcodes = ['en', 'it', 'fr', 'de', 'es'];
    $this->cardinality = 4;
    $this->unchangedFieldValues = [];

    // Set up an initial set of values in the correct state, that is with
    // "synchronized" values being equal.
    foreach ($this->langcodes as $langcode) {
      for ($delta = 0; $delta < $this->cardinality; $delta++) {
        foreach ($this->columns as $column) {
          $sync = in_array($column, $this->synchronized) && $langcode != $this->langcodes[0];
          $value = $sync ? $this->unchangedFieldValues[$this->langcodes[0]][$delta][$column] : $langcode . '-' . $delta . '-' . $column;
          $this->unchangedFieldValues[$langcode][$delta][$column] = $value;
        }
      }
    }
  }

  /**
   * Tests the field synchronization algorithm.
   */
  public function testFieldSync() {
    // Add a new item to the source items and check that its added to all the
    // translations.
    $sync_langcode = $this->langcodes[2];
    $unchanged_items = $this->unchangedFieldValues[$sync_langcode];
    $field_values = $this->unchangedFieldValues;
    $item = [];
    foreach ($this->columns as $column) {
      $item[$column] = $this->randomMachineName();
    }
    $field_values[$sync_langcode][] = $item;
    $this->synchronizer->synchronizeItems($field_values, $unchanged_items, $sync_langcode, $this->langcodes, $this->synchronized);
    $result = TRUE;
    foreach ($this->unchangedFieldValues as $langcode => $items) {
      // Check that the old values are still in place.
      for ($delta = 0; $delta < $this->cardinality; $delta++) {
        foreach ($this->columns as $column) {
          $result = $result && ($this->unchangedFieldValues[$langcode][$delta][$column] == $field_values[$langcode][$delta][$column]);
        }
      }
      // Check that the new item is available in all languages.
      foreach ($this->columns as $column) {
        $result = $result && ($field_values[$langcode][$delta][$column] == $field_values[$sync_langcode][$delta][$column]);
      }
    }
    $this->assertTrue($result, 'A new item has been correctly synchronized.');

    // Remove an item from the source items and check that its removed from all
    // the translations.
    $sync_langcode = $this->langcodes[1];
    $unchanged_items = $this->unchangedFieldValues[$sync_langcode];
    $field_values = $this->unchangedFieldValues;
    $sync_delta = mt_rand(0, count($field_values[$sync_langcode]) - 1);
    unset($field_values[$sync_langcode][$sync_delta]);
    // Renumber deltas to start from 0.
    $field_values[$sync_langcode] = array_values($field_values[$sync_langcode]);
    $this->synchronizer->synchronizeItems($field_values, $unchanged_items, $sync_langcode, $this->langcodes, $this->synchronized);
    $result = TRUE;
    foreach ($this->unchangedFieldValues as $langcode => $items) {
      $new_delta = 0;
      // Check that the old values are still in place.
      for ($delta = 0; $delta < $this->cardinality; $delta++) {
        // Skip the removed item.
        if ($delta != $sync_delta) {
          foreach ($this->columns as $column) {
            $result = $result && ($this->unchangedFieldValues[$langcode][$delta][$column] == $field_values[$langcode][$new_delta][$column]);
          }
          $new_delta++;
        }
      }
    }
    $this->assertTrue($result, 'A removed item has been correctly synchronized.');

    // Move the items around in the source items and check that they are moved
    // in all the translations.
    $sync_langcode = $this->langcodes[3];
    $unchanged_items = $this->unchangedFieldValues[$sync_langcode];
    $field_values = $this->unchangedFieldValues;
    $field_values[$sync_langcode] = [];
    // Scramble the items.
    foreach ($unchanged_items as $delta => $item) {
      $new_delta = ($delta + 1) % $this->cardinality;
      $field_values[$sync_langcode][$new_delta] = $item;
    }
    // Renumber deltas to start from 0.
    ksort($field_values[$sync_langcode]);
    $this->synchronizer->synchronizeItems($field_values, $unchanged_items, $sync_langcode, $this->langcodes, $this->synchronized);
    $result = TRUE;
    foreach ($field_values as $langcode => $items) {
      for ($delta = 0; $delta < $this->cardinality; $delta++) {
        foreach ($this->columns as $column) {
          $value = $field_values[$langcode][$delta][$column];
          if (in_array($column, $this->synchronized)) {
            // If we are dealing with a synchronize column the current value is
            // supposed to be the same of the source items.
            $result = $result && $field_values[$sync_langcode][$delta][$column] == $value;
          }
          else {
            // Otherwise the values should be unchanged.
            $old_delta = ($delta > 0 ? $delta : $this->cardinality) - 1;
            $result = $result && $this->unchangedFieldValues[$langcode][$old_delta][$column] == $value;
          }
        }
      }
    }
    $this->assertTrue($result, 'Scrambled items have been correctly synchronized.');
  }

  /**
   * Tests that items holding the same values are correctly synchronized.
   */
  public function testMultipleSyncedValues() {
    $sync_langcode = $this->langcodes[1];
    $unchanged_items = $this->unchangedFieldValues[$sync_langcode];

    // Determine whether the unchanged values should be altered depending on
    // their delta.
    $delta_callbacks = [
      // Continuous field values: all values are equal.
      function ($delta) {
        return TRUE;
      },
      // Alternated field values: only the even ones are equal.
      function ($delta) {
        return $delta % 2 !== 0;
      },
      // Sparse field values: only the "middle" ones are equal.
      function ($delta) {
        return $delta === 1 || $delta === 2;
      },
      // Sparse field values: only the "extreme" ones are equal.
      function ($delta) {
        return $delta === 0 || $delta === 3;
      },
    ];

    foreach ($delta_callbacks as $delta_callback) {
      $field_values = $this->unchangedFieldValues;

      for ($delta = 0; $delta < $this->cardinality; $delta++) {
        if ($delta_callback($delta)) {
          foreach ($this->columns as $column) {
            if (in_array($column, $this->synchronized)) {
              $field_values[$sync_langcode][$delta][$column] = $field_values[$sync_langcode][0][$column];
            }
          }
        }
      }

      $changed_items = $field_values[$sync_langcode];
      $this->synchronizer->synchronizeItems($field_values, $unchanged_items, $sync_langcode, $this->langcodes, $this->synchronized);

      foreach ($this->unchangedFieldValues as $langcode => $unchanged_items) {
        for ($delta = 0; $delta < $this->cardinality; $delta++) {
          foreach ($this->columns as $column) {
            // The first item is always unchanged hence it is retained by the
            // synchronization process. The other ones are retained or synced
            // depending on the logic implemented by the delta callback and
            // whether it is a sync column or not.
            $value = $delta > 0 && $delta_callback($delta) && in_array($column, $this->synchronized) ? $changed_items[0][$column] : $unchanged_items[$delta][$column];
            $this->assertEqual($field_values[$langcode][$delta][$column], $value, "Item $delta column $column for langcode $langcode synced correctly");
          }
        }
      }
    }
  }

  /**
   * Tests that one change in a synchronized column triggers a change in all columns.
   */
  public function testDifferingSyncedColumns() {
    $sync_langcode = $this->langcodes[2];
    $unchanged_items = $this->unchangedFieldValues[$sync_langcode];
    $field_values = $this->unchangedFieldValues;

    for ($delta = 0; $delta < $this->cardinality; $delta++) {
      $index = ($delta % 2) + 1;
      $field_values[$sync_langcode][$delta]['sync' . $index] .= '-updated';
    }

    $changed_items = $field_values[$sync_langcode];
    $this->synchronizer->synchronizeItems($field_values, $unchanged_items, $sync_langcode, $this->langcodes, $this->synchronized);

    foreach ($this->unchangedFieldValues as $langcode => $unchanged_items) {
      for ($delta = 0; $delta < $this->cardinality; $delta++) {
        foreach ($this->columns as $column) {
          // If the column is synchronized, the value should have been synced,
          // for unsychronized columns, the value must not change.
          $expected_value = in_array($column, $this->synchronized) ? $changed_items[$delta][$column] : $this->unchangedFieldValues[$langcode][$delta][$column];
          $this->assertEqual($field_values[$langcode][$delta][$column], $expected_value, "Differing Item $delta column $column for langcode $langcode synced correctly");
        }
      }
    }
  }

}
