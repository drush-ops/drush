<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\TestTools\Attribute\ShareEnvironment;
use Drush\Commands\core\JsonapiGetCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests jn:get, built from container services and driven with a
 * CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
#[CoversClass(JsonapiGetCommand::class)]
class JsonapiTest extends KernelTestBase
{
    use UserCreationTrait;

    protected static $modules = ['system', 'user', 'field', 'file', 'text', 'serialization', 'jsonapi', 'entity_test'];

    protected function setUpEnvironment(): void
    {
        $this->installEntitySchema('user');
        $this->installEntitySchema('entity_test');
        EntityTest::create(['name' => 'Llama'])->save();
        // Routes are built on demand, which creates tables. Build them once, so
        // that the tables belong to the shared environment.
        $this->container->get('router.builder')->rebuild();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The sub-request runs as the current user.
        $this->setUpCurrentUser([], ['view test entity']);
    }

    public function testCollection(): void
    {
        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['url' => 'jsonapi/entity_test/entity_test']));
        $display = $tester->getDisplay();
        // Pretty printed, slashes unescaped.
        $this->assertStringStartsWith("{\n    \"jsonapi\": {", $display);
        $this->assertStringContainsString('/jsonapi/entity_test/entity_test', $display);
        $this->assertStringNotContainsString('\/', $display);
        $data = json_decode($display, true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['data']);
        $this->assertSame('entity_test--entity_test', $data['data'][0]['type']);
        $this->assertSame('Llama', $data['data'][0]['attributes']['name']);
    }

    public function testYaml(): void
    {
        $tester = $this->tester();
        $this->assertSame(Command::SUCCESS, $tester->execute(['url' => 'jsonapi/entity_test/entity_test', '--format' => 'yaml']));
        $this->assertStringContainsString("type: entity_test--entity_test\n", $tester->getDisplay());
        $this->assertStringContainsString('name: Llama', $tester->getDisplay());
    }

    public function testUnknownPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The request to jsonapi/nope/nope returned status 404.');
        $this->tester()->execute(['url' => 'jsonapi/nope/nope']);
    }

    public function testModuleMissing(): void
    {
        $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
        $moduleHandler->method('moduleExists')->willReturn(false);
        $tester = new CommandTester(new JsonapiGetCommand($this->container->get('kernel'), $moduleHandler));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The jsonapi module is not installed.');
        $tester->execute(['url' => 'jsonapi/entity_test/entity_test']);
    }

    protected function tester(): CommandTester
    {
        return new CommandTester(new JsonapiGetCommand(
            $this->container->get('kernel'),
            $this->container->get('module_handler'),
        ));
    }
}
