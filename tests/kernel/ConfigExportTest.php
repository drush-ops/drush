<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Command\Exception\UserAbortException;
use Drupal\Core\Config\ConfigImporterFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\SyncFactory;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\config\ConfigExportCommand;
use Drush\Doctor\Commands\config\ConfigImportCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the dr-only copy of core's config:export.
 *
 * Based on core's \Drupal\KernelTests\Core\Config\ConfigExportCommandTest.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(ConfigExportCommand::class)]
class ConfigExportTest extends KernelTestBase
{
    protected static $modules = ['system', 'config_test'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installConfig(['system', 'config_test']);
        $this->config('system.site')->set('name', 'dr config')->save();
        // Overrides are not exported.
        $GLOBALS['config']['system.maintenance']['message'] = 'Foo';
    }

    public function testExport(): void
    {
        $this->assertSame('Foo', \Drupal::config('system.maintenance')->get('message'));
        $this->assertEmpty($this->sync()->listAll());

        $tester = $this->export(['--yes' => true]);
        $display = $this->normalize($tester);
        $this->assertStringContainsString('system.site Create', $display);
        $this->assertStringContainsString('config_test.dynamic.dotted.default Create', $display);
        $this->assertStringContainsString('Configuration successfully exported', $display);
        $this->assertNotSame('Foo', $this->sync()->read('system.maintenance')['message']);
        $this->assertStorageEquals($this->active(), $this->sync());

        // Nothing to export, so no table and no question.
        $display = $this->normalize($this->export(interactive: true));
        $this->assertStringContainsString('The active configuration to export matches the configuration in the sync directory.', $display);
        $this->assertStringNotContainsString('Do you want to export', $display);
    }

    /**
     * Updated, created and deleted config reaches the sync directory.
     */
    public function testChanges(): void
    {
        $this->export(['--yes' => true]);

        $this->config('system.site')->set('slogan', 'Changed')->save();
        $this->container->get('entity_type.manager')->getStorage('config_test')->create(['id' => 'new', 'label' => 'New'])->save();
        $this->config('config_test.system')->delete();
        $this->container->get('entity_type.manager')->getStorage('config_test')->load('dotted.default')->delete();

        $display = $this->normalize($this->export(['--yes' => true]));
        $this->assertStringContainsString('system.site Update', $display);
        $this->assertStringContainsString('config_test.dynamic.new Create', $display);
        $this->assertStringContainsString('config_test.system Delete', $display);
        $this->assertStringContainsString('config_test.dynamic.dotted.default Delete', $display);
        $this->assertSame('Changed', $this->sync()->read('system.site')['slogan']);
        $this->assertSame('New', $this->sync()->read('config_test.dynamic.new')['label']);
        $this->assertFalse($this->sync()->exists('config_test.system'));
        $this->assertFalse($this->sync()->exists('config_test.dynamic.dotted.default'));
        $this->assertStorageEquals($this->active(), $this->sync());
    }

    public function testConfirmation(): void
    {
        $this->export(['--yes' => true]);
        $expected = new MemoryStorage();
        $this->copyConfig($this->sync(), $expected);
        $this->config('system.site')->set('slogan', 'Changed')->save();

        // Declined.
        try {
            $this->export(inputs: ['no'], interactive: true);
            $this->fail('Expected a UserAbortException.');
        } catch (UserAbortException) {
        }
        $this->assertStorageEquals($expected, $this->sync());

        // Accepted.
        $tester = $this->export(inputs: ['yes'], interactive: true);
        $this->assertStringContainsString('Do you want to export the configuration?', $tester->getDisplay());
        $this->assertSame('Changed', $this->sync()->read('system.site')['slogan']);

        // Without interaction the question takes its default, yes.
        $this->config('system.site')->set('slogan', 'Changed again')->save();
        $tester = $this->export();
        $this->assertStringNotContainsString('Do you want to export', $tester->getDisplay());
        $this->assertSame('Changed again', $this->sync()->read('system.site')['slogan']);
    }

    public function testUndefinedSyncDirectory(): void
    {
        $settings = Settings::getAll();
        unset($settings['config_sync_directory']);
        $tester = $this->export(['--yes' => true], expected: Command::FAILURE, settings: new Settings($settings));
        $this->assertStringContainsString('config sync directory is not defined', $tester->getDisplay());
    }

    /**
     * A round trip with the copy of core's config:import.
     */
    public function testRoundTrip(): void
    {
        $this->export(['--yes' => true]);
        $this->sync()->write('system.site', ['slogan' => 'Imported'] + $this->sync()->read('system.site'));

        $tester = new CommandTester(new ConfigImportCommand(
            $this->active(),
            $this->sync(),
            $this->container->get('config.import_transformer'),
            $this->container->get(ConfigImporterFactory::class),
        ));
        $this->assertSame(Command::SUCCESS, $tester->execute(['--yes' => true]), $tester->getDisplay());
        $this->assertStringContainsString('system.site Update', $this->normalize($tester));
        $this->assertSame('Imported', $this->config('system.site')->get('slogan'));

        // And back. The import sorted the module list in core.extension,
        // which KernelTestBase leaves unsorted, so that is exported as well.
        $this->config('system.site')->set('slogan', 'Exported')->save();
        $this->assertStringContainsString('system.site Update', $this->normalize($this->export()));
        $this->assertSame('Exported', $this->sync()->read('system.site')['slogan']);
        $this->assertStorageEquals($this->active(), $this->sync());
    }

    protected function export(array $input = [], array $inputs = [], bool $interactive = false, int $expected = Command::SUCCESS, ?Settings $settings = null): CommandTester
    {
        // The SyncFactory service is private, so build it by hand.
        $syncFactory = new SyncFactory(
            $this->container->getParameter('app.root'),
            $settings ?? Settings::getInstance(),
            $this->active(),
            $this->container->get('extension.list.module'),
            $this->container->get('extension.list.theme'),
            $this->container->get('config.parsing_cache'),
        );
        $exportStorage = $this->container->get('config.storage.export');
        // The export storage is built once per request. Reset it so that it
        // sees config changed since the previous run.
        (function () {
            unset($this->storage);
        })->call($exportStorage);
        $tester = new CommandTester(new ConfigExportCommand($exportStorage, $syncFactory));
        $tester->setInputs($inputs);
        $this->assertSame($expected, $tester->execute($input, ['interactive' => $interactive]), $tester->getDisplay());
        return $tester;
    }

    /**
     * The display with whitespace and table borders collapsed.
     */
    protected function normalize(CommandTester $tester): string
    {
        return trim((string) preg_replace('/[\s|+-]*\s+[\s|+-]*/', ' ', $tester->getDisplay()));
    }

    protected function active(): StorageInterface
    {
        return $this->container->get('config.storage');
    }

    protected function sync(): StorageInterface
    {
        return $this->container->get('config.storage.sync');
    }

    protected static function assertStorageEquals(StorageInterface $expected, StorageInterface $actual): void
    {
        static::assertEqualsCanonicalizing($expected->getAllCollectionNames(), $actual->getAllCollectionNames());
        foreach (array_merge([StorageInterface::DEFAULT_COLLECTION], $expected->getAllCollectionNames()) as $collection) {
            $expectedCollection = $expected->createCollection($collection);
            $actualCollection = $actual->createCollection($collection);
            static::assertEqualsCanonicalizing($expectedCollection->listAll(), $actualCollection->listAll());
            foreach ($expectedCollection->listAll() as $name) {
                static::assertEquals($expectedCollection->read($name), $actualCollection->read($name), $name);
            }
        }
    }
}
