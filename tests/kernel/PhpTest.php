<?php

declare(strict_types=1);

namespace Unish;

use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\core\PhpEvalCommand;
use Drush\Commands\core\PhpScriptCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests php:eval and php:script without a BootstrapManager: the kernel test
 * already provides a Drupal container.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(PhpEvalCommand::class)]
#[CoversClass(PhpScriptCommand::class)]
class PhpTest extends KernelTestBase
{
    protected static $modules = ['system'];

    public function testEvalScalar(): void
    {
        $tester = $this->eval(['code' => 'return 2 + 3']);
        $this->assertSame("5\n", $tester->getDisplay());

        $tester = $this->eval(['code' => 'return "hi"']);
        $this->assertSame("'hi'\n", $tester->getDisplay());
    }

    public function testEvalFormats(): void
    {
        $code = "return ['a' => 1, 'b' => ['c', 'd']]";
        $expected = ['a' => 1, 'b' => ['c', 'd']];

        $json = $this->eval(['code' => $code, '--format' => 'json'])->getDisplay();
        $this->assertSame($expected, json_decode($json, true));
        $this->assertStringContainsString("\n", trim($json), 'json output is pretty printed');

        $yaml = $this->eval(['code' => $code, '--format' => 'yaml'])->getDisplay();
        $this->assertSame("a: 1\nb:\n  - c\n  - d\n", $yaml);

        // Without --format an array prints via var_export().
        $display = $this->eval(['code' => $code])->getDisplay();
        $this->assertSame($expected, eval('return ' . $display . ';'));
    }

    public function testEvalNullIsQuiet(): void
    {
        $this->assertSame('', $this->eval(['code' => 'return null'])->getDisplay());
        $this->assertSame('', $this->eval(['code' => '$a = 1'])->getDisplay());
    }

    public function testEvalUsesContainer(): void
    {
        $tester = $this->eval(['code' => 'return \Drupal::service("module_handler")->moduleExists("system")']);
        $this->assertSame("true\n", $tester->getDisplay());
    }

    public function testScriptByPath(): void
    {
        $tester = $this->script(['extra' => [__DIR__ . '/resources/unish_script.php'], '--format' => 'json']);
        $result = json_decode($tester->getDisplay(), true);
        $this->assertTrue($result['system']);
        $this->assertSame([], $result['extra']);
    }

    public function testScriptPathAndExtra(): void
    {
        // Found through --script-path, without the .php suffix.
        $tester = $this->script([
            'extra' => ['unish_script', 'apple', '--cider'],
            '--script-path' => '/no/such/dir' . PATH_SEPARATOR . __DIR__ . '/resources',
            '--format' => 'json',
        ]);
        $this->assertSame(['apple', '--cider'], json_decode($tester->getDisplay(), true)['extra']);
    }

    public function testScriptStdin(): void
    {
        $tester = new CommandTester(new PhpScriptCommand());
        $tester->setInputs(['<?php print "from stdin";']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['extra' => ['-']]));
        // setInputs() terminates each input with a newline.
        $this->assertSame('<?php print "from stdin";' . PHP_EOL, $tester->getDisplay());
    }

    public function testScriptListing(): void
    {
        $tester = $this->script(['--script-path' => __DIR__ . '/resources']);
        $listed = explode("\n", trim($tester->getDisplay()));
        $this->assertContains('unish_script.php', $listed);
        $this->assertContains('unish_exit.php', $listed);
    }

    public function testScriptMissing(): void
    {
        $tester = new CommandTester(new PhpScriptCommand());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Unable to find any of the following: %s/no_such_script, %s/resources/no_such_script', getcwd(), __DIR__));
        $tester->execute(['extra' => ['no_such_script'], '--script-path' => __DIR__ . '/resources']);
    }

    public function testScriptExitCode(): void
    {
        $tester = new CommandTester(new PhpScriptCommand());
        $this->assertSame(3, $tester->execute(['extra' => ['unish_exit'], '--script-path' => __DIR__ . '/resources']));
        $this->assertSame("3\n", $tester->getDisplay());
    }

    protected function eval(array $input): CommandTester
    {
        $tester = new CommandTester(new PhpEvalCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    protected function script(array $input): CommandTester
    {
        $tester = new CommandTester(new PhpScriptCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }
}
