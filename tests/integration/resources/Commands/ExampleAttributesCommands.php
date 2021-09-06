<?php
namespace Drush\Commands;

// Copy either of the lines below into your commandfile. It is a matter of taste.
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Attributes as DR;
use Consolidation\AnnotatedCommand\Attributes as AC;
use Drush\Boot\DrupalBootLevels;

class ExampleAttributesCommands extends DrushCommands
{
    #[CLI\Command(name: 'my:echo', aliases: ['c'])]
    #[CLI\Help(description: 'This is the my:echo command', synopsis: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.")]
    #[CLI\Param(name: 'one', description: 'The first parameter')]
    #[CLI\Param(name: 'two', description: 'The other parameter')]
    #[CLI\Option(name: 'flip', description: 'Whether or not the second parameter should come first in the result.')]
    #[CLI\Usage(name: 'bet alpha --flip', description: 'Concatenate "alpha" and "bet".')]
    public function myEcho($one, $two = '', array $options = ['flip' => false])
    {
        if ($options['flip']) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }

    #[CLI\Command(name: 'improved:echo', aliases: ['c'])]
    #[CLI\Help(description: 'This is the improved:echo command', synopsis: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.")]
    #[CLI\Param(name: 'args', description: 'Any number of arguments separated by spaces.')]
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
    #[CLI\Param(name: 'one', description: 'The first number to add.')]
    #[CLI\Param(name: 'two', description: 'The other number to add.')]
    #[CLI\Option(name: 'negate', description: 'Whether or not the result should be negated.')]
    #[CLI\Usage(name: '2 2 --negate', description: 'Add two plus two and then negate.')]
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
    #[AC\Param(name: 'permissions', description: 'A list of permissions.')]
    #[AC\Param(name: 'paths', description: 'A list of paths.')]
    #[AC\Param(name: 'roleName', description: 'A role name')]
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
}
