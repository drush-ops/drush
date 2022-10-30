<?php

namespace Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Attributes as DR;
use Consolidation\AnnotatedCommand\Attributes as AC;
use Drush\Boot\DrupalBootLevels;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

class ExampleAttributesCommands extends DrushCommands
{
    #[CLI\Command(name: 'my:echo', aliases: ['c'])]
    #[CLI\Help(description: 'This is the my:echo command', synopsis: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.")]
    #[CLI\Argument(name: 'one', description: 'The first parameter')]
    #[CLI\Argument(name: 'two', description: 'The other parameter')]
    #[CLI\Option(name: 'flip', description: 'Whether or not the second parameter should come first in the result.')]
    #[CLI\Usage(name: 'bet alpha --flip', description: 'Concatenate "alpha" and "bet".')]
    #[CLI\Version(version: '11.0')]
    public function myEcho($one, $two = '', array $options = ['flip' => false])
    {
        if ($options['flip']) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }

    #[CLI\Command(name: 'improved:echo', aliases: ['c'])]
    #[CLI\Help(description: 'This is the improved:echo command', synopsis: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.")]
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

    #[CLI\Command(name: 'test:arithmatic', aliases: ['arithmatic'])]
    #[CLI\Help(description: 'This is the test:arithmatic command', synopsis: "This command will add one and two. If the --negate flag\nis provided, then the result is negated.")]
    #[CLI\Argument(name: 'one', description: 'The first number to add.')]
    #[CLI\Argument(name: 'two', description: 'The other number to add.')]
    #[CLI\Option(name: 'negate', description: 'Whether or not the result should be negated.')]
    #[CLI\Usage(name: '2 2 --negate', description: 'Add two plus two and then negate.')]
    #[CLI\Complete(method_name_or_callable: 'testArithmaticComplete')]
    #[CLI\Misc(data: ['dup' => ['one', 'two']])]
    public function testArithmatic($one, $two = 2, array $options = ['negate' => false, 'unused' => 'bob'])
    {
        $result = $one + $two;
        if ($options['negate']) {
            $result = -$result;
        }

        // Integer return codes are exit codes (errors), so
        // return the result as a string so that it will be printed.
        return "$result";
    }

    // Declare a hook with a target.
    #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'test:arithmatic')]
    #[CLI\Help(description: 'Add a text after test:arithmatic command')]
    public function postArithmatic()
    {
        $this->output->writeln('HOOKED');
    }

    // Use lots of hooks including ValidateEntityLoad and Bootstrap.
    #[AC\Command(name: 'validatestuff')]
    #[AC\Help(description: 'Exercise some validators')]
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

    #[CLI\Command(name: 'birds')]
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
        if ($input->mustSuggestArgumentValuesFor('one') || $input->mustSuggestArgumentValuesFor('two')) {
            $suggestions->suggestValues(range(0, 9));
        }
    }
}
