<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\cache\CacheClearCommand;
use Drush\Commands\cache\CacheGetCommand;
use Drush\Commands\cache\CacheSetCommand;
use Drush\Commands\cache\CacheTagsCommand;
use Drush\Commands\cache\CacheWarmCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the cache commands: each command is built from container services
 * and driven with a CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(CacheGetCommand::class)]
#[CoversClass(CacheSetCommand::class)]
#[CoversClass(CacheTagsCommand::class)]
#[CoversClass(CacheClearCommand::class)]
#[CoversClass(CacheWarmCommand::class)]
class CacheTest extends KernelTestBase
{
    protected static $modules = ['system'];

    protected CacheBackendInterface $bin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bin = $this->container->get('cache_factory')->get('default');
    }

    public function testSetAndGetString(): void
    {
        $tester = $this->set(['cid' => 'foo', 'data' => 'bar']);
        $this->assertStringContainsString('Cached foo in the default bin.', $tester->getDisplay());
        $this->assertSame('bar', $this->bin->get('foo')->data);

        // json is the default output.
        $json = json_decode($this->get(['cid' => 'foo'])->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('foo', $json['cid']);
        $this->assertSame('bar', $json['data']);
        $this->assertSame(-1, $json['expire']);
        $this->assertSame([], $json['tags']);
        $this->assertTrue($json['valid']);

        $yaml = Yaml::decode($this->get(['cid' => 'foo', '--format' => 'yaml'])->getDisplay());
        $this->assertSame($json, $yaml);
    }

    public function testJsonFromStdinWithCacheGet(): void
    {
        $expected = ['key' => 'value'];
        $tester = new CommandTester(new CacheSetCommand($this->container->get('cache_factory')));
        $tester->setInputs([json_encode(['data' => $expected])]);
        $status = $tester->execute([
            'cid' => 'from-stdin',
            'data' => '-',
            'bin' => 'default',
            'expire' => 'CACHE_PERMANENT',
            '--input-format' => 'json',
            '--cache-get' => true,
        ]);
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertSame($expected, $this->bin->get('from-stdin')->data);

        // A cache:get result round-trips through cache:set.
        $output = $this->get(['cid' => 'from-stdin'])->getDisplay();
        $this->set(['cid' => 'copy', 'data' => $output, '--input-format' => 'json', '--cache-get' => true]);
        $this->assertSame($expected, $this->bin->get('copy')->data);
    }

    public function testTagsInvalidate(): void
    {
        $this->set(['cid' => 'tagged', 'data' => 'x', 'bin' => 'default', 'expire' => 'CACHE_PERMANENT', 'tags' => 'node:1,user:2']);
        $json = json_decode($this->get(['cid' => 'tagged'])->getDisplay(), true);
        $this->assertSame(['node:1', 'user:2'], $json['tags']);

        $tester = new CommandTester(new CacheTagsCommand($this->container->get('cache_tags.invalidator')));
        $this->assertSame(Command::SUCCESS, $tester->execute(['tags' => 'node:1']));
        $this->assertStringContainsString('Invalidated tag(s): node:1.', $tester->getDisplay());

        $this->assertFalse($this->bin->get('tagged'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The tagged object in the default bin was not found.');
        $this->getTester()->execute(['cid' => 'tagged']);
    }

    public function testGetMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The nope object in the default bin was not found.');
        $this->getTester()->execute(['cid' => 'nope']);
    }

    public function testUnknownInputFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown input format: xml');
        $this->setTester()->execute(['cid' => 'foo', 'data' => 'bar', '--input-format' => 'xml']);
    }

    public function testClearInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'nope' cache is not a valid cache type.");
        $this->clearTester()->execute(['type' => 'nope']);
    }

    public function testClearRender(): void
    {
        $this->bin->set('rendered-item', 'x', CacheBackendInterface::CACHE_PERMANENT, ['rendered']);
        $this->bin->set('other-item', 'y');

        $tester = $this->clearTester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['type' => 'render']));
        $this->assertStringContainsString("'render' cache was cleared.", $tester->getDisplay());
        $this->assertFalse($this->bin->get('rendered-item'));
        $this->assertSame('y', $this->bin->get('other-item')->data);
    }

    public function testClearBin(): void
    {
        $this->bin->set('item', 'x');
        $tester = $this->clearTester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['type' => 'bin', 'args' => ['default']]));
        $this->assertStringContainsString('Cleared cache bin(s): default.', $tester->getDisplay());
        $this->assertFalse($this->bin->get('item'));
    }

    public function testNoCacheClear(): void
    {
        $this->bin->set('item', 'x');
        $tester = $this->clearTester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['type' => 'bin', 'args' => ['default'], '--no-cache-clear' => true]));
        $this->assertSame('x', $this->bin->get('item')->data);
    }

    public function testWarm(): void
    {
        $tester = new CommandTester(new CacheWarmCommand($this->container->get('cache_prewarmer')));
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Warmed caches.', $tester->getDisplay());
    }

    protected function setTester(): CommandTester
    {
        return new CommandTester(new CacheSetCommand($this->container->get('cache_factory')));
    }

    protected function getTester(): CommandTester
    {
        return new CommandTester(new CacheGetCommand($this->container->get('cache_factory')));
    }

    protected function clearTester(): CommandTester
    {
        $c = $this->container;
        return new CommandTester(new CacheClearCommand(
            $c->get('cache_factory'),
            $c->get('cache_tags.invalidator'),
            $c->get('theme.registry'),
            $c->get('router.builder'),
            $c->get('asset.js.collection_optimizer'),
            $c->get('asset.css.collection_optimizer'),
            $c->get('plugin.cache_clearer'),
            $c->get('kernel'),
            $c->get('asset.query_string'),
            $c->get('event_dispatcher'),
        ));
    }

    protected function set(array $input): CommandTester
    {
        $tester = $this->setTester();
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }

    protected function get(array $input): CommandTester
    {
        $tester = $this->getTester();
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }
}
