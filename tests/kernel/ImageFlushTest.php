<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Commands\core\ImageFlushCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests image:flush, built from container services and driven with a
 * CommandTester. Derivatives are created with image:derive first.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(ImageFlushCommand::class)]
class ImageFlushTest extends ImageTestBase
{
    public function testFlushOne(): void
    {
        $this->derive('small');
        $this->derive('tiny');

        $tester = $this->flushTester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['style-names' => 'small']));
        $this->assertStringContainsString('Image style small flushed.', $tester->getDisplay());
        $this->assertStringNotContainsString('tiny', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->derivativeUri('small'));
        $this->assertFileExists($this->derivativeUri('tiny'));
    }

    public function testFlushAll(): void
    {
        $this->derive('small');
        $this->derive('tiny');

        $tester = $this->flushTester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        $this->assertStringContainsString('Image style small flushed.', $tester->getDisplay());
        $this->assertStringContainsString('Image style tiny flushed.', $tester->getDisplay());
        $this->assertFileDoesNotExist($this->derivativeUri('small'));
        $this->assertFileDoesNotExist($this->derivativeUri('tiny'));
    }

    public function testFlushInteractive(): void
    {
        $this->derive('small');
        $this->derive('tiny');

        $tester = $this->flushTester();
        $tester->setInputs(['tiny']);
        $this->assertSame(Command::SUCCESS, $tester->execute([], ['interactive' => true]));
        $this->assertStringContainsString('Choose a style to flush', $tester->getDisplay());
        $this->assertStringContainsString('Image style tiny flushed.', $tester->getDisplay());
        $this->assertFileExists($this->derivativeUri('small'));
        $this->assertFileDoesNotExist($this->derivativeUri('tiny'));
    }

    public function testNothingGiven(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Specify image style names to flush, or --all.');
        $this->flushTester()->execute([], ['interactive' => false]);
    }

    public function testUnknownStyle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to load the image_style: nope, nada');
        $this->flushTester()->execute(['style-names' => 'small,nope,nada']);
    }

    public function testImageModuleRequired(): void
    {
        $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
        $moduleHandler->method('moduleExists')->willReturn(false);
        $tester = new CommandTester(new ImageFlushCommand($this->entityTypeManager, $moduleHandler));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The image module is not installed.');
        $tester->execute(['--all' => true]);
    }

    protected function flushTester(): CommandTester
    {
        return new CommandTester(new ImageFlushCommand($this->entityTypeManager, $this->moduleHandler));
    }
}
