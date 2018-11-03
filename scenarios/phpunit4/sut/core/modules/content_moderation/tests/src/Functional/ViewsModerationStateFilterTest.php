<?php

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests the views 'moderation_state_filter' filter plugin.
 *
 * @coversDefaultClass \Drupal\content_moderation\Plugin\views\filter\ModerationStateFilter
 *
 * @group content_moderation
 */
class ViewsModerationStateFilterTest extends ViewTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_moderation_test_views',
    'node',
    'content_moderation',
    'workflows',
    'workflow_type_test',
    'entity_test',
    'language',
    'content_translation',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp(FALSE);

    NodeType::create([
      'type' => 'example_a',
    ])->save();
    NodeType::create([
      'type' => 'example_b',
    ])->save();

    $this->createEditorialWorkflow();

    $new_workflow = Workflow::create([
      'type' => 'content_moderation',
      'id' => 'new_workflow',
      'label' => 'New workflow',
    ]);
    $new_workflow->getTypePlugin()->addState('bar', 'Bar');
    $new_workflow->save();

    $this->drupalLogin($this->drupalCreateUser(['administer workflows', 'administer views']));
  }

  /**
   * Tests the dependency handling of the moderation state filter.
   *
   * @covers ::calculateDependencies
   * @covers ::onDependencyRemoval
   */
  public function testModerationStateFilterDependencyHandling() {
    // First, check that the view doesn't have any config dependency when there
    // are no states configured in the filter.
    $view_id = 'test_content_moderation_state_filter_base_table';
    $view = Views::getView($view_id);

    $this->assertWorkflowDependencies([], $view);
    $this->assertTrue($view->storage->status());

    // Configure the Editorial workflow for a node bundle, set the filter value
    // to use one of its states and check that the workflow is now a dependency
    // of the view.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/type/node', [
      'bundles[example_a]' => TRUE,
    ], 'Save');

    $edit['options[value][]'] = ['editorial-published'];
    $this->drupalPostForm("admin/structure/views/nojs/handler/$view_id/default/filter/moderation_state", $edit, 'Apply');
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');

    $view = Views::getView($view_id);
    $this->assertWorkflowDependencies(['editorial'], $view);
    $this->assertTrue($view->storage->status());

    // Create another workflow and repeat the checks above.
    $this->drupalPostForm('admin/config/workflow/workflows/add', [
      'label' => 'Translation',
      'id' => 'translation',
      'workflow_type' => 'content_moderation',
    ], 'Save');
    $this->drupalPostForm('admin/config/workflow/workflows/manage/translation/add_state', [
      'label' => 'Needs Review',
      'id' => 'needs_review',
    ], 'Save');
    $this->drupalPostForm('admin/config/workflow/workflows/manage/translation/type/node', [
      'bundles[example_b]' => TRUE,
    ], 'Save');

    $edit['options[value][]'] = ['editorial-published', 'translation-needs_review'];
    $this->drupalPostForm("admin/structure/views/nojs/handler/$view_id/default/filter/moderation_state", $edit, 'Apply');
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');

    $view = Views::getView($view_id);
    $this->assertWorkflowDependencies(['editorial', 'translation'], $view);
    $this->assertTrue(isset($view->storage->getDisplay('default')['display_options']['filters']['moderation_state']));
    $this->assertTrue($view->storage->status());

    // Remove the 'Translation' workflow.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/translation/delete', [], 'Delete');

    // Check that the view has been disabled, the filter has been deleted, the
    // view can be saved and there are no more config dependencies.
    $view = Views::getView($view_id);
    $this->assertFalse($view->storage->status());
    $this->assertFalse(isset($view->storage->getDisplay('default')['display_options']['filters']['moderation_state']));
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');
    $this->assertWorkflowDependencies([], $view);
  }

  /**
   * Tests the moderation state filter when the configured workflow is changed.
   *
   * @dataProvider providerTestWorkflowChanges
   */
  public function testWorkflowChanges($view_id, $filter_name) {
    // Update the view and make the default filter not exposed anymore,
    // otherwise all results will be shown when there are no more moderated
    // bundles left.
    $this->drupalPostForm("admin/structure/views/nojs/handler/$view_id/default/filter/moderation_state", [], 'Hide filter');
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');

    // First, apply the Editorial workflow to both of our content types.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/type/node', [
      'bundles[example_a]' => TRUE,
      'bundles[example_b]' => TRUE,
    ], 'Save');
    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();

    // Add a few nodes in various moderation states.
    $this->createNode(['type' => 'example_a', 'moderation_state' => 'published']);
    $this->createNode(['type' => 'example_b', 'moderation_state' => 'published']);
    $archived_node_a = $this->createNode(['type' => 'example_a', 'moderation_state' => 'archived']);
    $archived_node_b = $this->createNode(['type' => 'example_b', 'moderation_state' => 'archived']);

    // Configure the view to only show nodes in the 'archived' moderation state.
    $edit['options[value][]'] = ['editorial-archived'];
    $this->drupalPostForm("admin/structure/views/nojs/handler/$view_id/default/filter/moderation_state", $edit, 'Apply');
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');

    // Check that only the archived nodes from both bundles are displayed by the
    // view.
    $view = Views::getView($view_id);
    $this->executeView($view);
    $this->assertIdenticalResultset($view, [['nid' => $archived_node_a->id()], ['nid' => $archived_node_b->id()]], ['nid' => 'nid']);

    // Remove the Editorial workflow from one of the bundles.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/type/node', [
      'bundles[example_a]' => TRUE,
      'bundles[example_b]' => FALSE,
    ], 'Save');
    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();

    $view = Views::getView($view_id);
    $this->executeView($view);
    $this->assertIdenticalResultset($view, [['nid' => $archived_node_a->id()]], ['nid' => 'nid']);

    // Check that the view can still be edited and saved without any
    // intervention.
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');

    // Remove the Editorial workflow from both bundles.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/type/node', [
      'bundles[example_a]' => FALSE,
      'bundles[example_b]' => FALSE,
    ], 'Save');
    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();

    $view = Views::getView($view_id);
    $this->executeView($view);

    // Check that the view doesn't return any result.
    $this->assertEmpty($view->result);

    // Check that the view can not be edited without any intervention anymore
    // because the user needs to fix the filter.
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');
    $this->assertSession()->pageTextContains("No valid values found on filter: $filter_name.");
  }

  /**
   * Data provider for testWorkflowChanges.
   *
   * @return string[]
   *   An array of view IDs.
   */
  public function providerTestWorkflowChanges() {
    return [
      'view on base table, filter on base table' => [
        'test_content_moderation_state_filter_base_table',
        'Content: Moderation state',
      ],
      'view on base table, filter on revision table' => [
        'test_content_moderation_state_filter_base_table_filter_on_revision',
        'Content revision: Moderation state',
      ],
    ];
  }

  /**
   * Tests the content moderation state filter caching is correct.
   */
  public function testFilterRenderCache() {
    // Initially all states of the workflow are displayed.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/type/node', [
      'bundles[example_a]' => TRUE,
    ], 'Save');
    $this->assertFilterStates(['All', 'editorial-draft', 'editorial-published', 'editorial-archived']);

    // Adding a new state to the editorial workflow will display that state in
    // the list of filters.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/add_state', [
      'label' => 'Foo',
      'id' => 'foo',
    ], 'Save');
    $this->assertFilterStates(['All', 'editorial-draft', 'editorial-published', 'editorial-archived', 'editorial-foo']);

    // Adding a second workflow to nodes will also show new states.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/new_workflow/type/node', [
      'bundles[example_b]' => TRUE,
    ], 'Save');
    $this->assertFilterStates(['All', 'editorial-draft', 'editorial-published', 'editorial-archived', 'editorial-foo', 'new_workflow-draft', 'new_workflow-published', 'new_workflow-bar']);

    // Add a few more states and change the exposed filter to allow multiple
    // selections so we can check that the size of the select element does not
    // exceed 8 options.
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/add_state', [
      'label' => 'Foo 2',
      'id' => 'foo2',
    ], 'Save');
    $this->drupalPostForm('admin/config/workflow/workflows/manage/editorial/add_state', [
      'label' => 'Foo 3',
      'id' => 'foo3',
    ], 'Save');

    $view_id = 'test_content_moderation_state_filter_base_table';
    $edit['options[expose][multiple]'] = TRUE;
    $this->drupalPostForm("admin/structure/views/nojs/handler/$view_id/default/filter/moderation_state", $edit, 'Apply');
    $this->drupalPostForm("admin/structure/views/view/$view_id", [], 'Save');

    $this->assertFilterStates(['editorial-draft', 'editorial-published', 'editorial-archived', 'editorial-foo', 'editorial-foo2', 'editorial-foo3', 'new_workflow-draft', 'new_workflow-published', 'new_workflow-bar'], TRUE);
  }

  /**
   * Assert the states which appear in the filter.
   *
   * @param array $states
   *   The states which should appear in the filter.
   * @param bool $check_size
   *   (optional) Whether to check that size of the select element is not
   *   greater than 8. Defaults to FALSE.
   */
  protected function assertFilterStates($states, $check_size = FALSE) {
    $this->drupalGet('/filter-test-path');

    $assert_session = $this->assertSession();

    // Check that the select contains the correct number of options.
    $assert_session->elementsCount('css', '#edit-default-revision-state option', count($states));

    // Check that the size of the select element does not exceed 8 options.
    if ($check_size) {
      $this->assertGreaterThan(8, count($states));
      $assert_session->elementAttributeContains('css', '#edit-default-revision-state', 'size', 8);
    }

    // Check that an option exists for each of the expected states.
    foreach ($states as $state) {
      $assert_session->optionExists('Default Revision State', $state);
    }
  }

  /**
   * Asserts the views dependencies on workflow config entities.
   *
   * @param string[] $workflow_ids
   *   An array of workflow IDs to check.
   * @param \Drupal\views\ViewExecutable $view
   *   An executable View object.
   */
  protected function assertWorkflowDependencies(array $workflow_ids, ViewExecutable $view) {
    $dependencies = $view->getDependencies();

    $expected = [];
    foreach (Workflow::loadMultiple($workflow_ids) as $workflow) {
      $expected[] = $workflow->getConfigDependencyName();
    }

    if ($expected) {
      $this->assertSame($expected, $dependencies['config']);
    }
    else {
      $this->assertTrue(!isset($dependencies['config']));
    }
  }

}
