<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\VersionCommand;
use Drush\Drush;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the version command, which needs neither Drupal nor a container.
 */
#[CoversClass(VersionCommand::class)]
#[Group('base')]
class VersionTest extends TestCase
{
    public function testDefault(): void
    {
        $expected = 'Drush version : ' . Drush::sanitizeVersionString(Drush::getVersion()) . "\n";
        $this->assertSame($expected, $this->version([])->getDisplay());
    }

    /**
     * DrushTestTrait::drushMajorVersion() relies on this output.
     */
    public function testField(): void
    {
        $this->assertSame(Drush::getVersion() . "\n", $this->version(['--field' => 'drush-version'])->getDisplay());
    }

    public function testFormats(): void
    {
        $json = $this->version(['--format' => 'json'])->getDisplay();
        $this->assertSame(['drush-version' => Drush::getVersion()], json_decode($json, true));
        $yaml = $this->version(['--format' => 'yaml'])->getDisplay();
        $this->assertSame('drush-version: ' . Drush::getVersion() . "\n", $yaml);
    }

    public function testUnknownField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field nope.');
        new CommandTester(new VersionCommand())->execute(['--field' => 'nope']);
    }

    protected function version(array $input): CommandTester
    {
        $tester = new CommandTester(new VersionCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }
}
