<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\core\ImageDeriveCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests image:derive, built from container services and driven with a
 * CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(ImageDeriveCommand::class)]
class ImageDeriveTest extends ImageTestBase
{
    public function testDerive(): void
    {
        $derivative = $this->derivativeUri('small');
        $this->assertFileDoesNotExist($derivative);

        $tester = $this->deriveTester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['style-name' => 'small', 'source' => self::SOURCE]));
        $this->assertStringContainsString('Derivative image created: ' . $derivative, $tester->getDisplay());
        $this->assertFileExists($derivative);
    }

    public function testUnknownStyle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to load the image_style: nope');
        $this->deriveTester()->execute(['style-name' => 'nope', 'source' => self::SOURCE]);
    }

    public function testMissingSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File(s) not found: public://missing.png');
        $this->deriveTester()->execute(['style-name' => 'small', 'source' => 'public://missing.png']);
    }

    public function testImageModuleRequired(): void
    {
        $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
        $moduleHandler->method('moduleExists')->willReturn(false);
        $tester = new CommandTester(new ImageDeriveCommand($this->entityTypeManager, $moduleHandler));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The image module is not installed.');
        $tester->execute(['style-name' => 'small', 'source' => self::SOURCE]);
    }
}
