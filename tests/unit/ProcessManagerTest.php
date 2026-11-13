<?php

declare(strict_types=1);

namespace Drush\SiteAlias;

use Consolidation\SiteAlias\SiteAlias;
use Drush\Config\DrushConfig;
use Drush\DrupalFinder\DrushDrupalFinder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Tests which Drush script ProcessManager uses for sub-processes.
 */
class ProcessManagerTest extends TestCase
{
    protected string $projectDir;

    protected mixed $composerBinDir;

    protected function setUp(): void
    {
        // relativePathToVendorBinDrush() prefers the bin dir of the Composer
        // proxy that started the test runner; use the fixture's instead.
        $this->composerBinDir = $GLOBALS['_composer_bin_dir'] ?? null;
        unset($GLOBALS['_composer_bin_dir']);

        $this->projectDir = Path::canonicalize(sys_get_temp_dir() . '/drush-process-manager-' . uniqid());
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->projectDir . '/web');
        $filesystem->dumpFile($this->projectDir . '/vendor/bin/drush', '');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
        if ($this->composerBinDir !== null) {
            $GLOBALS['_composer_bin_dir'] = $this->composerBinDir;
        }
    }

    /**
     * A local alias without a root uses the vendor/bin/drush of the Drupal site.
     *
     * On Windows the 'runtime.drush-script' (drush.php) cannot be executed
     * directly, so updatedb would silently find "No pending updates".
     */
    public function testLocalAliasWithoutRootUsesVendorBinDrush(): void
    {
        $processManager = $this->processManager($this->projectDir . '/web');
        $alias = new SiteAlias(['uri' => 'example.com'], '@example');

        $this->assertSame($this->projectDir . '/vendor/bin/drush', $processManager->drushScript($alias));
    }

    public function testLocalAliasWithRootUsesItsVendorBinDrush(): void
    {
        $processManager = $this->processManager($this->projectDir . '/web');
        $alias = new SiteAlias(['root' => $this->projectDir . '/web', 'uri' => 'example.com'], '@example');

        $this->assertSame($this->projectDir . '/vendor/bin/drush', Path::canonicalize($processManager->drushScript($alias)));
    }

    public function testWithoutDrupalRootFallsBackToRuntimeDrushScript(): void
    {
        $processManager = $this->processManager(false);
        $alias = new SiteAlias(['uri' => 'example.com'], '@example');

        $this->assertSame('/path/to/drush.php', $processManager->drushScript($alias));
    }

    public function testWithoutVendorBinDrushFallsBackToRuntimeDrushScript(): void
    {
        (new Filesystem())->remove($this->projectDir . '/vendor/bin/drush');
        $processManager = $this->processManager($this->projectDir . '/web');
        $alias = new SiteAlias(['uri' => 'example.com'], '@example');

        $this->assertSame('/path/to/drush.php', $processManager->drushScript($alias));
    }

    /**
     * Creates a ProcessManager for a Drupal root (or none) in the fixture.
     */
    protected function processManager(string|false $drupalRoot): ProcessManager
    {
        $drupalFinder = $this->createStub(DrushDrupalFinder::class);
        $drupalFinder->method('getDrupalRoot')->willReturn($drupalRoot);
        $drupalFinder->method('getVendorDir')->willReturn($this->projectDir . '/vendor');

        $config = new DrushConfig();
        $config->set('runtime.drush-script', '/path/to/drush.php');

        $processManager = new ProcessManager();
        $processManager->setConfig($config);
        $processManager->setDrupalFinder($drupalFinder);
        return $processManager;
    }
}
