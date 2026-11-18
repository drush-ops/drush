<?php

declare(strict_types=1);

namespace Unish;

use Drupal\KernelTests\KernelTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use Drush\Commands\core\BrowseCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the browse command, which prints the absolute URL of a site path.
 *
 * KernelTestBase boots the kernel with a request for '/' on host localhost,
 * so absolute URLs resolve to http://localhost/ without further setup.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
#[CoversClass(BrowseCommand::class)]
class BrowseTest extends KernelTestBase
{
    protected static $modules = ['system'];

    protected function setUpEnvironment(): void
    {
        // Generating a URL builds the router on demand, which creates tables.
        // Build it once, so that they belong to the shared environment.
        $this->container->get('router.builder')->rebuild();
    }

    public function testFrontPage(): void
    {
        $link = $this->browse([]);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/$#', $link);
    }

    public function testPath(): void
    {
        $link = $this->browse(['path' => 'node/1']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/node/1$#', $link);

        // A leading slash is tolerated.
        $this->assertSame($link, $this->browse(['path' => '/node/1']));
    }

    public function testQueryString(): void
    {
        $link = $this->browse(['path' => 'node/1?foo=bar']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/node/1\?foo=bar$#', $link);
    }

    public function testInvalidPath(): void
    {
        $tester = new CommandTester(new BrowseCommand());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid site path');
        $tester->execute(['path' => '//example.com/evil']);
    }

    /**
     * Runs the command and returns the printed link.
     */
    protected function browse(array $input): string
    {
        $tester = new CommandTester(new BrowseCommand());
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return trim($tester->getDisplay());
    }
}
