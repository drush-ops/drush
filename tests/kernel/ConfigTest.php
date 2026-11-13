<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ConfigTestTrait;
use Drush\Commands\config\ConfigDeleteCommand;
use Drush\Commands\config\ConfigGetCommand;
use Drush\Commands\config\ConfigSetCommand;
use Drush\Commands\config\ConfigStatusCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests config:get, config:set, config:delete and config:status: each command is built from
 * container services and driven with a CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(ConfigDeleteCommand::class)]
#[CoversClass(ConfigGetCommand::class)]
#[CoversClass(ConfigSetCommand::class)]
#[CoversClass(ConfigStatusCommand::class)]
class ConfigTest extends KernelTestBase
{
    use ConfigTestTrait;

    protected static $modules = ['system'];

    /**
     * The tests write keys and objects that have no schema, as config:set allows.
     */
    protected $strictConfigSchema = false;

    protected ConfigFactoryInterface $configFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installConfig(['system']);
        $this->configFactory = $this->container->get('config.factory');
    }

    public function testGet(): void
    {
        $this->configFactory->getEditable('system.site')->set('name', 'Unish')->set('page.front', '/llama')->save();

        // The whole object, as yaml.
        $display = $this->configGet(['config_name' => 'system.site'])->getDisplay();
        $this->assertStringContainsString("name: Unish\n", $display);
        $this->assertStringContainsString("page:\n  403: ''\n  404: ''\n  front: /llama\n", $display);

        // A single key is keyed by config name and key.
        $this->assertSame("'system.site:name': Unish\n", $this->configGet(['config_name' => 'system.site', 'key' => 'name'])->getDisplay());
        $this->assertSame("'system.site:page.front': /llama\n", $this->configGet(['config_name' => 'system.site', 'key' => 'page.front'])->getDisplay());

        // Json.
        $json = $this->configGet(['config_name' => 'system.site', '--format' => 'json'])->getDisplay();
        $this->assertSame('Unish', json_decode($json, true)['name']);
        $json = $this->configGet(['config_name' => 'system.site', 'key' => 'page', '--format' => 'json'])->getDisplay();
        $this->assertSame(['system.site:page' => ['403' => '', '404' => '', 'front' => '/llama']], json_decode($json, true));
        $this->assertSame("'system.site:page.front': /llama\n", $this->configGet(['config_name' => 'system.site', 'key' => 'page.front', '--format' => 'yaml'])->getDisplay());
    }

    public function testGetOverridden(): void
    {
        $this->configFactory->getEditable('system.site')->set('name', 'Unish')->save();
        $GLOBALS['config']['system.site']['name'] = 'Overridden';

        $input = ['config_name' => 'system.site', 'key' => 'name'];
        $this->assertSame("'system.site:name': Unish\n", $this->configGet($input)->getDisplay());
        $this->assertSame("'system.site:name': Unish\n", $this->configGet($input + ['--no-include-overridden' => true])->getDisplay());
        $this->assertSame("'system.site:name': Overridden\n", $this->configGet($input + ['--include-overridden' => true])->getDisplay());
    }

    public function testGetUnknownName(): void
    {
        $tester = new CommandTester(new ConfigGetCommand($this->configFactory));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config no.such.config does not exist');
        $tester->execute(['config_name' => 'no.such.config']);
    }

    public function testGetPromptsForName(): void
    {
        $tester = new CommandTester(new ConfigGetCommand($this->configFactory));
        $tester->setInputs(['system.site']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['key' => 'langcode'], ['interactive' => true]));
        $this->assertStringContainsString('Choose a configuration', $tester->getDisplay());
        $this->assertStringContainsString("'system.site:langcode': en", $tester->getDisplay());
    }

    public function testSetString(): void
    {
        $this->configSet(['config_name' => 'system.site', 'key' => 'name', 'value' => 'config_test']);
        $this->assertSame('config_test', $this->config('system.site')->get('name'));

        // A nested key.
        $this->configSet(['config_name' => 'system.site', 'key' => 'page.front', 'value' => 'llama']);
        $this->assertSame('llama', $this->config('system.site')->get('page.front'));

        // A new key, and a new config object.
        $this->configSet(['config_name' => 'system.site', 'key' => 'unish', 'value' => 'new key']);
        $this->assertSame('new key', $this->config('system.site')->get('unish'));
        $this->configSet(['config_name' => 'unish.settings', 'key' => 'foo', 'value' => 'bar']);
        $this->assertSame('bar', $this->config('unish.settings')->get('foo'));
    }

    public function testSetTypedWords(): void
    {
        // The words true, false and null become typed values, in any case.
        $this->configSet(['config_name' => 'system.performance', 'key' => 'css.preprocess', 'value' => 'false']);
        $this->assertFalse($this->config('system.performance')->get('css.preprocess'));
        $this->configSet(['config_name' => 'system.performance', 'key' => 'css.preprocess', 'value' => 'TRUE']);
        $this->assertTrue($this->config('system.performance')->get('css.preprocess'));
        $this->configSet(['config_name' => 'system.site', 'key' => 'slogan', 'value' => 'NULL']);
        $this->assertNull($this->config('system.site')->get('slogan'));
        $this->configSet(['config_name' => 'unish.settings', 'key' => 'list', 'value' => '[]']);
        $this->assertSame([], $this->config('unish.settings')->get('list'));
    }

    public function testSetYaml(): void
    {
        $this->configSet(['config_name' => 'unish.settings', 'key' => 'permissions', 'value' => '[foo,bar]', '--input-format' => 'yaml']);
        $this->assertSame(['foo', 'bar'], $this->config('unish.settings')->get('permissions'));

        $this->configSet(['config_name' => 'system.site', 'key' => 'page', 'value' => "{403: '403', front: home}", '--input-format' => 'yaml']);
        $this->assertSame(['403' => '403', 'front' => 'home'], $this->config('system.site')->get('page'));

        // Without --input-format the same text is saved as a string.
        $this->configSet(['config_name' => 'unish.settings', 'key' => 'text', 'value' => '[foo,bar]']);
        $this->assertSame('[foo,bar]', $this->config('unish.settings')->get('text'));
    }

    public function testSetMultipleKeys(): void
    {
        $this->configSet(['config_name' => 'system.site', 'key' => '?', 'value' => "{name: 'Multi', weight_select_max: 5}", '--input-format' => 'yaml']);
        $this->assertSame('Multi', $this->config('system.site')->get('name'));
        $this->assertSame(5, $this->config('system.site')->get('weight_select_max'));
        $this->assertSame('en', $this->config('system.site')->get('langcode'), 'Other keys are left alone.');

        // The ? key needs a mapping.
        $tester = new CommandTester(new ConfigSetCommand($this->configFactory));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The ? key needs a mapping');
        $tester->execute(['config_name' => 'system.site', 'key' => '?', 'value' => 'scalar'], ['interactive' => false]);
    }

    public function testSetFromStdin(): void
    {
        $tester = new CommandTester(new ConfigSetCommand($this->configFactory));
        $tester->setInputs(["name: Stdin\nslogan: Piped"]);
        $status = $tester->execute(['config_name' => 'system.site', 'key' => '?', 'value' => '-', '--input-format' => 'yaml'], ['interactive' => false]);
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame('Stdin', $this->config('system.site')->get('name'));
        $this->assertSame('Piped', $this->config('system.site')->get('slogan'));
    }

    public function testSetDeclined(): void
    {
        $this->configFactory->getEditable('system.site')->set('name', 'Unish')->save();

        $questions = [
            'Do you want to update name key in system.site config?' => ['config_name' => 'system.site', 'key' => 'name', 'value' => 'changed'],
            'unish key does not exist in system.site config.' => ['config_name' => 'system.site', 'key' => 'unish', 'value' => 'changed'],
            'unish.settings config does not exist.' => ['config_name' => 'unish.settings', 'key' => 'name', 'value' => 'changed'],
            'Do you want to update or set multiple keys on system.site config.' => ['config_name' => 'system.site', 'key' => '?', 'value' => '{name: changed}', '--input-format' => 'yaml'],
        ];
        foreach ($questions as $question => $input) {
            $tester = new CommandTester(new ConfigSetCommand($this->configFactory));
            $tester->setInputs(['no']);
            $this->assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => true]));
            $this->assertStringContainsString($question, $tester->getDisplay());
        }
        $this->assertSame('Unish', $this->config('system.site')->get('name'));
        $this->assertNull($this->config('system.site')->get('unish'));
        $this->assertTrue($this->config('unish.settings')->isNew());

        // Accepting saves.
        $tester = new CommandTester(new ConfigSetCommand($this->configFactory));
        $tester->setInputs(['yes']);
        $tester->execute(['config_name' => 'system.site', 'key' => 'name', 'value' => 'changed'], ['interactive' => true]);
        $this->assertSame('changed', $this->config('system.site')->get('name'));
    }

    public function testSetUnknownInputFormat(): void
    {
        $tester = new CommandTester(new ConfigSetCommand($this->configFactory));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown input format: xml');
        $tester->execute(['config_name' => 'system.site', 'key' => 'name', 'value' => 'x', '--input-format' => 'xml']);
    }

    public function testDelete(): void
    {
        // A single key.
        $this->configDelete(['config_name' => 'system.site', 'key' => 'page.front']);
        $this->assertNull($this->config('system.site')->get('page.front'));
        $this->assertSame('', $this->config('system.site')->get('page.403'), 'Other keys are left alone.');

        // Whole objects, comma delimited.
        $this->configDelete(['config_name' => 'system.site, system.date']);
        $this->assertTrue($this->config('system.site')->isNew());
        $this->assertTrue($this->config('system.date')->isNew());
        $this->assertFalse($this->config('system.performance')->isNew());
    }

    public function testDeleteUnknownName(): void
    {
        $tester = new CommandTester(new ConfigDeleteCommand($this->configFactory));
        try {
            $tester->execute(['config_name' => 'system.performance,no.such.config']);
            $this->fail('Expected an exception.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Config no.such.config does not exist', $e->getMessage());
        }
        // Nothing is deleted when one of the names is unknown.
        $this->assertFalse($this->config('system.performance')->isNew());
    }

    public function testDeleteUnknownKey(): void
    {
        $tester = new CommandTester(new ConfigDeleteCommand($this->configFactory));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration key no.such.key not found.');
        $tester->execute(['config_name' => 'system.site', 'key' => 'no.such.key']);
    }

    public function testDeleteKeyWithMultipleNames(): void
    {
        $tester = new CommandTester(new ConfigDeleteCommand($this->configFactory));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A key may not be used with multiple config names.');
        $tester->execute(['config_name' => 'system.site,system.date', 'key' => 'langcode']);
    }

    public function testStatus(): void
    {
        $active = $this->container->get('config.storage');
        $sync = $this->container->get('config.storage.sync');
        $this->copyConfig($active, $sync);

        // No differences: a note, and no table.
        $display = $this->configStatus()->getDisplay();
        $this->assertStringContainsString('No differences between DB and sync directory.', $display);
        $this->assertStringNotContainsString('system.site', $display);

        // Json and yaml still print an empty result on stdout.
        $tester = $this->configStatus(['--format' => 'json'], ['capture_stderr_separately' => true]);
        $this->assertSame("[]\n", $tester->getDisplay());
        $this->assertStringContainsString('No differences', $tester->getErrorOutput());

        // Change one object, add one, and remove one from active config.
        $this->configFactory->getEditable('system.site')->set('name', 'Changed')->save();
        $this->configFactory->getEditable('unish.settings')->set('foo', 'bar')->save();
        $this->configFactory->getEditable('system.date')->delete();

        $display = $this->configStatus()->getDisplay();
        $this->assertMatchesRegularExpression('/Name\s+\|\s+State/', $display);
        $this->assertMatchesRegularExpression('/system\.site\s+\|\s+Different/', $display);
        $this->assertMatchesRegularExpression('/unish\.settings\s+\|\s+Only in DB/', $display);
        $this->assertMatchesRegularExpression('/system\.date\s+\|\s+Only in sync dir/', $display);
        $this->assertStringNotContainsString('Identical', $display);
        $this->assertStringNotContainsString('No differences', $display);

        // --state.
        $display = $this->configStatus(['--state' => 'Different'])->getDisplay();
        $this->assertStringContainsString('system.site', $display);
        $this->assertStringNotContainsString('unish.settings', $display);
        $this->assertStringNotContainsString('system.date', $display);

        $display = $this->configStatus(['--state' => 'Identical'])->getDisplay();
        $this->assertMatchesRegularExpression('/system\.performance\s+\|\s+Identical/', $display);
        $this->assertStringNotContainsString('system.site', $display);

        $display = $this->configStatus(['--state' => 'Any'])->getDisplay();
        $this->assertStringContainsString('system.performance', $display);
        $this->assertStringContainsString('system.site', $display);

        // --prefix.
        $display = $this->configStatus(['--prefix' => 'unish.'])->getDisplay();
        $this->assertStringContainsString('unish.settings', $display);
        $this->assertStringNotContainsString('system.site', $display);

        // Json and yaml are keyed by name, without colour tags.
        $expected = [
            'system.date' => ['name' => 'system.date', 'state' => 'Only in sync dir'],
            'system.site' => ['name' => 'system.site', 'state' => 'Different'],
            'unish.settings' => ['name' => 'unish.settings', 'state' => 'Only in DB'],
        ];
        $this->assertSame($expected, json_decode($this->configStatus(['--format' => 'json'])->getDisplay(), true));
        $this->assertSame(
            "system.site:\n  name: system.site\n  state: Different\n",
            $this->configStatus(['--format' => 'yaml', '--state' => 'Different'])->getDisplay(),
        );
    }

    protected function configGet(array $input): CommandTester
    {
        $tester = new CommandTester(new ConfigGetCommand($this->configFactory));
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    /**
     * Runs config:set without prompting, which accepts the confirmation.
     */
    protected function configSet(array $input): CommandTester
    {
        $tester = new CommandTester(new ConfigSetCommand($this->configFactory));
        $this->assertSame(Command::SUCCESS, $tester->execute($input, ['interactive' => false]));
        return $tester;
    }

    protected function configDelete(array $input): CommandTester
    {
        $tester = new CommandTester(new ConfigDeleteCommand($this->configFactory));
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    protected function configStatus(array $input = [], array $options = []): CommandTester
    {
        $command = new ConfigStatusCommand(
            $this->configFactory,
            $this->container->get('config.storage'),
            $this->container->get('config.storage.sync'),
            $this->container->get('config.import_transformer'),
        );
        $tester = new CommandTester($command);
        $this->assertSame(Command::SUCCESS, $tester->execute($input, $options));
        return $tester;
    }
}
