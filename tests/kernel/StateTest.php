<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drush\Dr\standard\Command\state\StateDeleteCommand;
use Drush\Dr\standard\Command\state\StateGetCommand;
use Drush\Dr\standard\Command\state\StateSetCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the state commands: each command is built from the state service
 * and driven with a CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(StateGetCommand::class)]
#[CoversClass(StateSetCommand::class)]
#[CoversClass(StateDeleteCommand::class)]
class StateTest extends KernelTestBase
{
    protected static $modules = ['system'];

    protected StateInterface $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = $this->container->get('state');
    }

    public function testSetAndGetScalars(): void
    {
        // A plain string.
        $tester = $this->set(['key' => 'foo.string', 'value' => 'bar']);
        $this->assertStringContainsString("Set state entry foo.string to 'bar'", $tester->getDisplay());
        $this->assertSame('bar', $this->state->get('foo.string'));
        $this->assertSame("bar\n", $this->get(['key' => 'foo.string'])->getDisplay());

        // An explicit integer.
        $this->set(['key' => 'foo.int', 'value' => '42', '--input-format' => 'integer']);
        $this->assertSame(42, $this->state->get('foo.int'));
        $this->assertSame("42\n", $this->get(['key' => 'foo.int'])->getDisplay());

        // Numbers are detected without --input-format.
        $this->set(['key' => 'foo.auto', 'value' => '7']);
        $this->assertSame(7, $this->state->get('foo.auto'));

        // TRUE and FALSE become booleans.
        $this->set(['key' => 'foo.true', 'value' => 'TRUE']);
        $this->assertTrue($this->state->get('foo.true'));
        $this->assertSame("1\n", $this->get(['key' => 'foo.true'])->getDisplay());
        $this->set(['key' => 'foo.false', 'value' => 'FALSE']);
        $this->assertFalse($this->state->get('foo.false'));
        $this->assertSame('', $this->get(['key' => 'foo.false'])->getDisplay());
    }

    public function testStructuredInputAndOutput(): void
    {
        $expected = ['a' => 1, 'b' => ['c', 'd']];

        $this->set(['key' => 'foo.json', 'value' => json_encode($expected), '--input-format' => 'json']);
        $this->assertSame($expected, $this->state->get('foo.json'));

        $this->set(['key' => 'foo.yaml', 'value' => "a: 1\nb: [c, d]\n", '--input-format' => 'yaml']);
        $this->assertSame($expected, $this->state->get('foo.yaml'));

        // The encoded output is the bare value.
        $json = $this->get(['key' => 'foo.json', '--format' => 'json'])->getDisplay();
        $this->assertSame($expected, json_decode($json, true));
        $this->assertStringContainsString("\n", trim($json), 'json output is pretty printed');

        $yaml = $this->get(['key' => 'foo.json', '--format' => 'yaml'])->getDisplay();
        $this->assertSame("a: 1\nb:\n  - c\n  - d\n", $yaml);

        // Without --format an array falls back to yaml.
        $this->assertSame($yaml, $this->get(['key' => 'foo.json'])->getDisplay());

        // Scalars encode as bare values too.
        $this->set(['key' => 'foo.int', 'value' => '42', '--input-format' => 'integer']);
        $this->assertSame("42\n", $this->get(['key' => 'foo.int', '--format' => 'json'])->getDisplay());
    }

    public function testStdin(): void
    {
        $command = new StateSetCommand($this->state);
        $tester = new CommandTester($command);
        $tester->setInputs(['{"x": [1, 2]}']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['key' => 'foo.stdin', 'value' => '-', '--input-format' => 'json']));
        $this->assertSame(['x' => [1, 2]], $this->state->get('foo.stdin'));
    }

    public function testMissingKey(): void
    {
        foreach ([[], ['--format' => 'json'], ['--format' => 'yaml']] as $options) {
            $tester = $this->get(['key' => 'no.such.key'] + $options);
            $display = $tester->getDisplay();
            $this->assertTrue($display === '' || trim($display) === 'null', "Unexpected output: $display");
        }
    }

    public function testUnknownInputFormat(): void
    {
        $tester = new CommandTester(new StateSetCommand($this->state));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown input format: xml');
        $tester->execute(['key' => 'foo', 'value' => 'bar', '--input-format' => 'xml']);
    }

    public function testDelete(): void
    {
        $this->set(['key' => 'foo.gone', 'value' => 'bar']);
        $this->assertSame("bar\n", $this->get(['key' => 'foo.gone'])->getDisplay());

        $tester = new CommandTester(new StateDeleteCommand($this->state));
        $this->assertSame(Command::SUCCESS, $tester->execute(['key' => 'foo.gone']));
        $this->assertStringContainsString('Deleted state entry foo.gone', $tester->getDisplay());
        $this->assertNull($this->state->get('foo.gone'));
        $this->assertSame('', $this->get(['key' => 'foo.gone'])->getDisplay());
    }

    protected function set(array $input): CommandTester
    {
        $tester = new CommandTester(new StateSetCommand($this->state));
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    protected function get(array $input): CommandTester
    {
        $tester = new CommandTester(new StateGetCommand($this->state));
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }
}
