<?php

declare(strict_types=1);

namespace Unish;

use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\core\WhyConfigCommand;
use Drush\Commands\core\WhyModuleCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests why:module and why:config: each command is built from container
 * services and driven with a CommandTester. The dependent1-4 modules under
 * sut/modules/unish/drupal_dependencies provide the dependency chains.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(WhyModuleCommand::class)]
#[CoversClass(WhyConfigCommand::class)]
class WhyTest extends KernelTestBase
{
    protected static $modules = ['system', 'user'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installConfig(['system', 'user']);
    }

    public function testOptionsMismatch(): void
    {
        $this->assertInvalid('The --type option is mandatory.', ['module' => 'system']);
        $this->assertInvalid("The --type option can take only 'module' or 'config' as value.", ['module' => 'system', '--type' => 'wrong']);
        $this->assertInvalid(
            'Cannot use --type=config together with --no-only-installed.',
            ['module' => 'system', '--type' => 'config', '--no-only-installed' => true],
        );
    }

    public function testModuleDependentsOfModule(): void
    {
        // Node is not installed.
        $this->assertInvalid('Invalid node module.', ['module' => 'node', '--type' => 'module']);

        // Uninstalled modules count with --no-only-installed. Core's test
        // modules are discoverable in kernel tests, so check only the chain
        // the fixtures provide.
        $display = $this->whyModule(['module' => 'node', '--type' => 'module', '--no-only-installed' => true])->getDisplay();
        $this->assertStringStartsWith("node\n", $display);
        foreach (['dependent1', 'dependent2', 'dependent3', 'dependent4', 'taxonomy'] as $module) {
            $this->assertStringContainsString("─$module", $display);
        }

        $this->installModules(['node']);
        $this->assertStringContainsString(
            'No other module depends on node.',
            $this->whyModule(['module' => 'node', '--type' => 'module'])->getDisplay(),
        );

        $this->installModules(['taxonomy']);
        $expected = <<<EXPECTED
            node
            └─taxonomy

            EXPECTED;
        $this->assertSame($expected, $this->whyModule(['module' => 'node', '--type' => 'module'])->getDisplay());

        $this->installModules(['dependent3']);
        $expected = <<<EXPECTED
            node
            ├─dependent1
            │ └─dependent2
            │   └─dependent3
            ├─dependent2
            │ └─dependent3 (circular)
            └─taxonomy
              └─dependent1
                └─dependent2 (circular)

            EXPECTED;
        $this->assertSame($expected, $this->whyModule(['module' => 'node', '--type' => 'module'])->getDisplay());

        $json = $this->whyModule(['module' => 'node', '--type' => 'module', '--format' => 'json'])->getDisplay();
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
                'taxonomy' => [
                    'dependent1' => [
                        'dependent2' => 'dependent2:***circular***',
                    ],
                ],
            ],
        ];
        $this->assertSame($expected, json_decode($json, true));
    }

    public function testConfigDependentsOfModule(): void
    {
        // Node is not installed.
        $this->assertInvalid('Invalid node module.', ['module' => 'node', '--type' => 'config']);

        $this->installModules(['node']);
        $expected = <<<EXPECTED
            node
            ├─core.entity_view_mode.node.full
            ├─core.entity_view_mode.node.rss
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
        $this->assertSame(
            self::withoutBodyField($expected),
            self::withoutBodyField($this->whyModule(['module' => 'node', '--type' => 'config'])->getDisplay()),
        );

        $this->installModules(['dependent3']);
        $expected = <<<EXPECTED
            node
            ├─core.entity_view_mode.node.full
            ├─core.entity_view_mode.node.rss
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
        $this->assertSame(
            self::withoutBodyField($expected),
            self::withoutBodyField($this->whyModule(['module' => 'node', '--type' => 'config'])->getDisplay()),
        );
    }

    public function testConfigDependentsOfConfig(): void
    {
        // system.site is simple config, not a config entity.
        $tester = new CommandTester($this->whyConfigCommand());
        try {
            $tester->execute(['config' => 'system.site']);
            $this->fail('Expected an exception for a simple config name.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Invalid system.site config entity.', $e->getMessage());
        }

        $this->installModules(['dependent3']);

        $tester = new CommandTester($this->whyConfigCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute(['config' => 'node.type.vegetable']));
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
        $this->assertSame($expected, $tester->getDisplay());

        $tester = new CommandTester($this->whyConfigCommand());
        $tester->execute(['config' => 'field.field.node.vegetable.latin_name', '--format' => 'yaml']);
        $expected = <<<EXPECTED
            field.field.node.vegetable.latin_name:
              core.entity_form_display.node.vegetable.default: core.entity_form_display.node.vegetable.default
              core.entity_view_display.node.vegetable.default: core.entity_view_display.node.vegetable.default

            EXPECTED;
        $this->assertSame($expected, $tester->getDisplay());

        // Nothing depends on a form display.
        $tester = new CommandTester($this->whyConfigCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute(['config' => 'core.entity_form_display.node.vegetable.default']));
        // The note wraps at 80 columns.
        $this->assertStringContainsString('No other config entity depends on', $tester->getDisplay());
        $this->assertStringContainsString('core.entity_form_display.node.vegetable.default.', $tester->getDisplay());
    }

    private function whyModuleCommand(): WhyModuleCommand
    {
        return new WhyModuleCommand(
            $this->container->get('extension.list.module'),
            $this->container->get('module_handler'),
            $this->container->get('entity_type.manager'),
        );
    }

    private function whyConfigCommand(): WhyConfigCommand
    {
        return new WhyConfigCommand(
            $this->container->get('config.manager'),
            $this->container->get('entity_type.manager'),
        );
    }

    /**
     * Runs why:module and asserts success.
     */
    private function whyModule(array $input): CommandTester
    {
        $tester = new CommandTester($this->whyModuleCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    /**
     * Runs why:module and asserts that it rejects the input with $message.
     */
    private function assertInvalid(string $message, array $input): void
    {
        try {
            (new CommandTester($this->whyModuleCommand()))->execute($input);
            $this->fail("Expected: $message");
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    /**
     * Installs modules with their dependencies and default config.
     */
    private function installModules(array $modules): void
    {
        $this->container->get('module_installer')->install($modules);
    }

    /**
     * Removes the body field storage line from a dependency tree.
     *
     * Drupal 11 installs field.storage.node.body along with the node module,
     * but Drupal 12 no longer does.
     */
    private static function withoutBodyField(string $output): string
    {
        $lines = array_filter(
            explode("\n", $output),
            static fn(string $line): bool => !str_contains($line, 'field.storage.node.body'),
        );
        return implode("\n", $lines);
    }
}
