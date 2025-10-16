<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Utility\DeprecationHelper;

/**
 * @coversDefaultClass \Drush\Commands\core\DrupalDependenciesCommands
 */
class DrupalDependenciesTest extends UnishIntegrationTestCase
{
    /**
     * @covers ::dependentsOfModule
     */
    public function testModuleDependentOfModule(): void
    {
        $this->drush('list');
        $this->assertStringContainsString('why:module (wm)', $this->getOutput());
        $this->assertStringContainsString('List all objects (modules, configurations)', $this->getOutput());
        $this->assertStringContainsString('depending on a given module', $this->getOutput());

        // Trying to check an uninstalled module.
        $this->drush('why:module', ['node'], ['type' => 'module'], UnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString('Invalid node module', $this->getErrorOutput());

        // Check also uninstalled modules.
        $this->drush('wm', ['node'], [
            'type' => 'module',
            'no-only-installed' => null,
        ]);

        // In Drupal 10, book, forum, statistics and tracker modules were part
        // of Drupal core. Ensure a backwards compatible expectation.
        // @todo Remove the BC layer when Drupal 10 support is dropped.
        $expected = DeprecationHelper::backwardsCompatibleCall(
            \Drupal::VERSION,
            '11.0.0',
            fn() => <<<EXPECTED
                node
                ├─dependent1
                │ └─dependent2
                │   ├─dependent3
                │   └─dependent4
                ├─dependent2
                │ ├─dependent3
                │ └─dependent4
                ├─history
                │ └─dependent1
                │   └─dependent2 (circular)
                └─taxonomy
                  └─dependent1
                    └─dependent2 (circular)
                EXPECTED,
            // @deprecated
            fn() => <<<EXPECTED
                node
                ├─book
                ├─dependent1
                │ └─dependent2
                │   ├─dependent3
                │   └─dependent4
                ├─dependent2
                │ ├─dependent3
                │ └─dependent4
                ├─forum
                ├─history
                │ ├─dependent1
                │ │ └─dependent2 (circular)
                │ └─forum
                ├─statistics
                ├─taxonomy
                │ ├─dependent1
                │ │ └─dependent2 (circular)
                │ └─forum
                └─tracker
                EXPECTED,
        );
        $this->assertSame($expected, $this->getOutput());

        // Install node module.
        $this->drush('pm:install', ['node']);

        // No installed dependencies.
        $this->drush('why:module', ['node'], ['type' => 'module']);
        $this->assertSame('[notice] No other module depends on node', $this->getErrorOutput());

        $this->drush('pm:install', ['taxonomy']);
        $this->drush('wm', ['node'], ['type' => 'module']);
        $expected = <<<EXPECTED
            node
            └─taxonomy
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());

        $this->drush('pm:install', ['dependent3']);
        $this->drush('wm', ['node'], ['type' => 'module']);
        $expected = <<<EXPECTED
            node
            ├─dependent1
            │ └─dependent2
            │   └─dependent3
            ├─dependent2
            │ └─dependent3 (circular)
            ├─history
            │ └─dependent1
            │   └─dependent2 (circular)
            └─taxonomy
              └─dependent1
                └─dependent2 (circular)
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());

        // Test result formatted as JSON.
        $this->drush('wm', ['node'], [
            'type' => 'module',
            'format' => 'json',
        ]);
        $expected = [
            'node' => [
                'dependent1' => [
                    'dependent2' => [
                        'dependent3' => 'dependent3',
                    ],
                ],
                'dependent2' => [
                    'dependent3' => 'dependent3:***circular***',
                ],
                'history' => [
                    'dependent1' => [
                        'dependent2' => 'dependent2:***circular***',
                    ],
                ],
                'taxonomy' => [
                    'dependent1' => [
                        'dependent2' => 'dependent2:***circular***',
                    ],
                ],
            ],
        ];
        $this->assertSame($expected, $this->getOutputFromJSON());
    }

    /**
     * @covers ::validateDependentsOfModule
     */
    public function testOptionsMismatch(): void
    {
        $this->drush('why:module', ['node'], [], UnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString("The --type option is mandatory", $this->getErrorOutput());

        $this->drush('why:module', ['node'], ['type' => 'wrong'], UnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString(
            "The --type option can take only 'module' or 'config' as value",
            $this->getErrorOutput()
        );

        $this->drush('why:module', ['node'], [
            'type' => 'config',
            'no-only-installed' => null,
        ], UnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString(
            "Cannot use --type=config together with --no-only-installed",
            $this->getErrorOutput()
        );
    }

    /**
     * @covers ::dependentsOfModule
     */
    public function testConfigDependentOfModule(): void
    {
        // Trying to check an uninstalled module.
        $this->drush('why:module', ['node'], ['type' => 'config'], UnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString('Invalid node module', $this->getErrorOutput());

        // Install node module.
        $this->drush('pm:install', ['node']);

        // No installed dependencies.
        $this->drush('why:module', ['node'], ['type' => 'config']);
        $expected = <<<EXPECTED
            node
            ├─core.entity_view_mode.node.full
            ├─core.entity_view_mode.node.rss
            ├─core.entity_view_mode.node.search_index
            ├─core.entity_view_mode.node.search_result
            ├─core.entity_view_mode.node.teaser
            ├─field.storage.node.body
            ├─system.action.node_delete_action
            ├─system.action.node_make_sticky_action
            ├─system.action.node_make_unsticky_action
            ├─system.action.node_promote_action
            ├─system.action.node_publish_action
            ├─system.action.node_save_action
            ├─system.action.node_unpromote_action
            └─system.action.node_unpublish_action
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());

        $this->drush('pm:install', ['dependent3']);
        $this->drush('wm', ['node'], ['type' => 'config']);
        $expected = <<<EXPECTED
            node
            ├─core.entity_view_mode.node.full
            ├─core.entity_view_mode.node.rss
            ├─core.entity_view_mode.node.search_index
            ├─core.entity_view_mode.node.search_result
            ├─core.entity_view_mode.node.teaser
            ├─field.storage.node.body
            ├─field.storage.node.latin_name
            │ └─field.field.node.vegetable.latin_name
            │   ├─core.entity_form_display.node.vegetable.default
            │   └─core.entity_view_display.node.vegetable.default
            ├─field.storage.node.vegetable_type
            │ └─field.field.node.vegetable.vegetable_type
            │   ├─core.entity_form_display.node.vegetable.default
            │   └─core.entity_view_display.node.vegetable.default
            ├─system.action.node_delete_action
            ├─system.action.node_make_sticky_action
            ├─system.action.node_make_unsticky_action
            ├─system.action.node_promote_action
            ├─system.action.node_publish_action
            ├─system.action.node_save_action
            ├─system.action.node_unpromote_action
            └─system.action.node_unpublish_action
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());
    }

    /**
     * @covers ::dependentsOfConfig
     */
    public function testConfigDependentOfConfig(): void
    {
        $this->drush('why:config', ['system.site'], [], UnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString('Invalid system.site config entity', $this->getErrorOutput());

        // Install dependent3 module.
        $this->drush('pm:install', ['dependent3']);

        $this->drush('why:config', ['node.type.vegetable']);
        $expected = <<<EXPECTED
            node.type.vegetable
            ├─core.entity_form_display.node.vegetable.default
            ├─core.entity_view_display.node.vegetable.default
            ├─field.field.node.vegetable.latin_name
            │ ├─core.entity_form_display.node.vegetable.default
            │ └─core.entity_view_display.node.vegetable.default
            └─field.field.node.vegetable.vegetable_type
              ├─core.entity_form_display.node.vegetable.default
              └─core.entity_view_display.node.vegetable.default
            EXPECTED;
        $this->assertSame($expected, $this->getOutput());
    }

    protected function tearDown(): void
    {
        try {
            $this->drush('pmu', ['node,history,taxonomy,comment,dependent3'], ['yes' => null]);
        } catch (\Exception) {
            // The modules were not installed.
        }
        parent::tearDown();
    }
}
