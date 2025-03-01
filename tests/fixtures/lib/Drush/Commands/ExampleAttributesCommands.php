<?php

namespace Custom\Library\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Attributes as DR;
use Consolidation\AnnotatedCommand\Attributes as AC;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

class ExampleAttributesCommands extends DrushCommands
{
    const ARITHMATIC = 'test:arithmatic';
    const ECHO = 'my:echo';
    const VALIDATESTUFF = 'validatestuff';
    const BIRDS = 'birds';

    #[CLI\Command(name: self::ECHO, aliases: ['c'])]
    #[CLI\Help(description: 'This is the my:echo command', synopsis: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.", hidden: true)]
    #[CLI\Argument(name: 'one', description: 'The first parameter')]
    #[CLI\Argument(name: 'two', description: 'The other parameter')]
    #[CLI\Option(name: 'flip', description: 'Whether or not the second parameter should come first in the result.')]
    #[CLI\Usage(name: 'bet alpha --flip', description: 'Concatenate "alpha" and "bet".')]
    #[CLI\Version(version: '11.0')]
    public function myEcho($one, $two = '', $flip = false)
    {
        if ($flip) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }

    #[CLI\Command(name: 'improved:echo', aliases: ['c'])]
    #[CLI\Help(description: 'This is the improved:echo command', synopsis: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.", hidden: true)]
    #[CLI\Argument(name: 'args', description: 'Any number of arguments separated by spaces.')]
    #[CLI\Option(name: 'flip', description: 'Whether or not the second parameter should come first in the result.')]
    #[CLI\Usage(name: 'bet alpha --flip', description: 'Concatenate "alpha" and "bet".')]
    public function improvedEcho(array $args, $flip = false)
    {
        if ($flip) {
            $args = array_reverse($args);
        }
        return implode(' ', $args);
    }

    #[CLI\Command(name: self::ARITHMATIC, aliases: ['arithmatic'])]
    #[CLI\Help(description: 'This is the test:arithmatic command', synopsis: "This command will add one and two. If the --negate flag\nis provided, then the result is negated.", hidden: true)]
    // suggestedValues available on Symfony 6.1+. Also see the CLI\Complete Attribute below.
    #[CLI\Argument(name: 'one', description: 'The first number to add.', suggestedValues: [1,2,3,4,5])]
    #[CLI\Argument(name: 'two', description: 'The other number to add.')]
    // Use the Complete Attribute when for dynamic values.
    #[CLI\Complete(method_name_or_callable: 'testArithmaticComplete')]
    #[CLI\Option(name: 'negate', description: 'Whether or not the result should be negated.')]
    #[CLI\Option(name: 'color', description: 'What color are you feeling.', suggestedValues: ['red', 'blue', 'green'])]
    #[CLI\Usage(name: '2 2 --negate', description: 'Add two plus two and then negate.')]
    public function testArithmatic($one, $two = 2, $negate = false, $color = self::REQ)
    {
        $result = $one + $two;
        if ($negate) {
            $result = -$result;
        }

        // Integer return codes are exit codes (errors), so
        // return the result as a string so that it will be printed.
        return "$result";
    }

    // Declare a hook with a target.
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: self::ARITHMATIC)]
    #[CLI\Help(description: 'Add a text after test:arithmatic command')]
    public function postArithmatic()
    {
        $this->output->writeln('HOOKED');
    }

    // Use lots of hooks including ValidateEntityLoad and Bootstrap.
    #[AC\Command(name: self::VALIDATESTUFF)]
    #[AC\Help(description: 'Exercise some validators', hidden: true)]
    #[AC\Argument(name: 'permissions', description: 'A list of permissions.')]
    #[AC\Argument(name: 'paths', description: 'A list of paths.')]
    #[AC\Argument(name: 'roleName', description: 'A role name')]
    #[DR\Bootstrap(level: DrupalBootLevels::FULL)]
    #[DR\ValidateEntityLoad(entityType: 'user_role', argumentName: 'roleName')]
    #[DR\ValidateFileExists(argName: 'paths')]
    #[DR\ValidatePhpExtensions(extensions: ['json'])]
    #[DR\ValidateModulesEnabled(modules: ['user'])]
    #[DR\ValidatePermissions(argName: 'permissions')]
    public function validateStuff($permissions, $paths, $roleName)
    {
        return 'Validators are happy';
    }

    #[CLI\Command(name: self::BIRDS)]
    #[CLI\Help(description: 'List birds and their color.', hidden: true)]
    #[CLI\FieldLabels(labels: ['name' => 'Name', 'color' => 'Color'])]
    #[CLI\DefaultFields(fields: ['color'])]
    #[CLI\FilterDefaultField(field: 'name')]
    public function birds(): RowsOfFields
    {
        $rows = [
            'bluebird' => ['name' => 'Bluebird', 'color' => 'blue'],
            'cardinal' => ['name' => 'Cardinal', 'color' => 'red'],
        ];
        return new RowsOfFields($rows);
    }

    /*
     * An argument completion callback.
     */
    public function testArithmaticComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('two')) {
            $suggestions->suggestValues(range(10, 15));
        }
    }
}
