<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\core\ImageDeriveCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Shared setup for the image command kernel tests.
 *
 * Two scale-only styles are created so the derivatives keep the png
 * extension whatever the GD build supports.
 */
abstract class ImageTestBase extends KernelTestBase
{
    protected static $modules = ['system', 'image', 'file', 'user'];

    const SOURCE = 'public://menu-expanded.png';

    protected EntityTypeManagerInterface $entityTypeManager;
    protected ModuleHandlerInterface $moduleHandler;

    protected function setUpEnvironment(): void
    {
        $this->installEntitySchema('file');
        $this->installEntitySchema('user');
        $this->installConfig(['system', 'image']);
        foreach (['small' => 20, 'tiny' => 10] as $name => $size) {
            $style = ImageStyle::create(['name' => $name, 'label' => ucfirst($name)]);
            $style->addImageEffect(['id' => 'image_scale', 'data' => ['width' => $size, 'height' => $size, 'upscale' => false]]);
            $style->save();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->moduleHandler = $this->container->get('module_handler');
        // Files are not part of the shared environment: every test method has
        // a site directory of its own.
        $dir = 'public://';
        $this->container->get('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
        copy($this->root . '/core/misc/menu-expanded.png', self::SOURCE);
    }

    protected function derivativeUri(string $style): string
    {
        return ImageStyle::load($style)->buildUri(self::SOURCE);
    }

    protected function derive(string $style): void
    {
        $this->assertSame(Command::SUCCESS, $this->deriveTester()->execute(['style-name' => $style, 'source' => self::SOURCE]));
        $this->assertFileExists($this->derivativeUri($style));
    }

    protected function deriveTester(): CommandTester
    {
        return new CommandTester(new ImageDeriveCommand($this->entityTypeManager, $this->moduleHandler));
    }
}
