<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * @group base
 */
class AnnotatedCommandCase extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    public function testGlobal()
    {
        $globalIncludes = $targetDir = Path::join(__DIR__, 'resources/global-includes');

        $options = [];

        // We modified the set of available Drush commands; we need to clear the Drush command cache
        $this->drush('cc', ['drush'], $options);

        // drush foobar
        $options['include'] = "$globalIncludes";
        $this->drush('foobar', [], $options);
        $output = $this->getOutput();
        $this->assertEquals('baz', $output);

        // Drush foobaz
        $this->drush('foobaz', [], $options);
        $output = $this->getOutput();
        $this->assertEquals('bar', $output);

        $options = [
            'yes' => null,
            'include' => $globalIncludes,
            'directory' => self::getSandbox(),
        ];

        $this->drush('generate', ['foo-example'], $options, null, null, self::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);

        $target = Path::join($options['directory'], 'foo.php');
        $actual = trim(file_get_contents($target));
        $this->assertEquals('Foo.', $actual);
        unlink($target);
    }

    public function siteWideCommands()
    {
        $this->drush('sut:simple');
        $output = $this->getErrorOutput();
        $this->assertContains("This is an example site-wide command committed to the repository in the SUT inside of the 'drush/Commands' directory.", $output);

        $this->drush('sut:nested');
        $output = $this->getErrorOutput();
        $this->assertContains("This is an example site-wide command committed to the repository in the SUT nested inside a custom/example-site-wide-command directory.", $output);

        $this->drush('sut:nested-src');
        $output = $this->getErrorOutput();
        $this->assertContains("This is an example site-wide command committed to the repository in the SUT nested inside a custom/example-site-wide-command/src directory.", $output);
    }

    public function testExecute()
    {
        $this->setUpDrupal(1, true);

        // Test the site-wide commands first
        $this->siteWideCommands();

        // Copy the 'woot' module over to the Drupal site we just set up.
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));

        // Enable our module. This will also clear the commandfile cache.
        $this->drush('pm-enable', ['woot']);

        // In theory this is not necessary, but this test keeps failing.
        // $this->drush('cc', array('drush'), $options);

        // Make sure that modules can supply DCG Generators and they work.
        $optionsExample['answers'] = json_encode([
            'name' => 'foo',
            'machine_name' => 'bar',
        ]);
        $optionsExample['directory'] = self::webrootSlashDrush();
        $optionsExample['yes'] = null;
        $this->drush('generate', ['woot-example'], $optionsExample, null, null, self::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);
        $target = Path::join($optionsExample['directory'], 'Commands/ExampleBarCommands.php');
        $actual = trim(file_get_contents($target));
        $this->assertEquals('ExampleBarCommands says Woot mightily.', $actual);
        unlink($target);

        // drush woot
        $this->drush('woot');
        $output = $this->getOutput();
        $this->assertEquals('Woot!', $output);

        // drush my-cat bet alpha --flip
        $this->drush('my-cat', ['bet', 'alpha'], ['flip' => null]);
        $output = $this->getOutput();
        $this->assertEquals('alphabet', $output);

        // drush my-cat bet alpha --flip
        $this->drush('my-cat', ['bet', 'alpha'], ['flip' => null, 'ignored-modules' => 'woot'], null, null, self::EXIT_ERROR);

        $this->drush('try-formatters');
        $output = $this->getOutput();
        $expected = <<<EOT
 ------ ------ -------
  I      II     III
 ------ ------ -------
  One    Two    Three
  Eins   Zwei   Drei
  Ichi   Ni     San
  Uno    Dos    Tres
 ------ ------ -------
EOT;
        $this->assertEquals(trim(preg_replace('#[ \n]+#', ' ', $expected)), trim(preg_replace('#[ \n]+#', ' ', $output)));

        $this->drush('try-formatters --format=yaml --fields=III,II', [], [], null, null, self::EXIT_SUCCESS);
        $output = $this->getOutput();
        // TODO: If there are different versions of symfony/yaml in Drush and Drupal,
        // then we can get indentation errors. Ignore that in these tests; this is not
        // a problem with site-local Drush.
        $output = str_replace('    ', '  ', $output);
        $expected = <<<EOT
en:
  third: Three
  second: Two
de:
  third: Drei
  second: Zwei
jp:
  third: San
  second: Ni
es:
  third: Tres
  second: Dos
EOT;
        $this->assertEquals($expected, $output);

        $this->drush('try-formatters', [], ['format' => 'json']);
        $data = $this->getOutput();
        $expected = <<<EOT
{
    "en": {
        "first": "One",
        "second": "Two",
        "third": "Three"
    },
    "de": {
        "first": "Eins",
        "second": "Zwei",
        "third": "Drei"
    },
    "jp": {
        "first": "Ichi",
        "second": "Ni",
        "third": "San"
    },
    "es": {
        "first": "Uno",
        "second": "Dos",
        "third": "Tres"
    }
}
EOT;
        $this->assertEquals($expected, $data);

        // drush help my-cat
        $this->drush('help', ['my-cat']);
        $output = $this->getOutput();
        $this->assertContains('bet alpha --flip Concatinate "alpha" and "bet".', $output);
        $this->assertContains('Aliases: c', $output);

        // drush help woot
        $this->drush('help', ['woot']);
        $output = $this->getOutput();
        $this->assertContains('Woot mightily.', $output);

        // TODO: support console.command commands
        $this->drush('annotated:greet symfony');
        $output = $this->getOutput();
        $this->assertEquals('Hello symfony', $output);

        $this->drush('demo:greet symfony');
        $output = $this->getOutput();
        $this->assertEquals('Hello symfony', $output);

        $this->markTestSkipped('--help not working yet.');

        // drush my-cat --help
        $this->drush('my-cat', [], ['help' => null]);
        $output = $this->getOutput();
        $this->assertContains('my-cat bet alpha --flip', $output);
        $this->assertContains('The first parameter', $output);
        $this->assertContains('The other parameter', $output);
        $this->assertContains('Whether or not the second parameter', $output);

        // drush woot --help
        $this->drush('woot', [], ['help' => null]);
        $output = $this->getOutput();
        $this->assertContains('Usage:', $output);
        $this->assertContains('woot [options]', $output);
        $this->assertContains('Woot mightily.', $output);

        // drush try-formatters --help
        $this->drush('try-formatters', [], ['help' => null]);
        $output = $this->getOutput();
        $this->assertContains('Demonstrate formatters', $output);
        $this->assertContains('try:formatters --fields=first,third', $output);
        $this->assertContains('try:formatters --fields=III,II', $output);
        // $this->assertContains('--fields=<first, second, third>', $output);
        $this->assertContains('Available fields:', $output);
        $this->assertContains('[default: "table"]', $output);

        $this->markTestSkipped('--ignored-modules not supported yet');

        // TODO: Support --ignored-modules
        // drush woot --help with the 'woot' module ignored
        $this->drush('woot', [], ['help' => null, 'ignored-modules' => 'woot'], null, null, self::EXIT_ERROR);
    }

    public function setupGlobalExtensionsForTests()
    {
        $globalExtension = __DIR__ . '/resources/global-includes';
        $targetDir = Path::join(self::getSandbox(), 'global-includes');
        $this->mkdir($targetDir);
        $this->recursiveCopy($globalExtension, $targetDir);
        return $targetDir;
    }
}
