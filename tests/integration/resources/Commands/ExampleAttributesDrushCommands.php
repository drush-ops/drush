<?php
namespace Drush\Commands;

use Consolidation\AnnotatedCommand\CommandLineAttributes;

/**
 * Borrowed from https://github.com/beejeebus/annotated-command/blob/58e677d96b70845ce54bf0c367ddd09568f10044/tests/src/ExampleAttributesCommandFile.php.
 */
class ExampleAttributesDrushCommands extends DrushCommands
{
    #[CommandLineAttributes(
        name: 'my:echo',
        description: 'This is the my:echo command',
        help: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.",
        aliases: ['c'],
        usage: ['bet alpha --flip' => 'Concatenate "alpha" and "bet".'],
        options: [
            'flip' => [
                'description' => 'Whether or not the second parameter should come first in the result. Default: false'
            ]
        ]
    )]
    public function myEcho($one, $two = '', array $options = ['flip' => false])
    {
        if ($options['flip']) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }

    #[CommandLineAttributes(
        name: 'test:arithmatic',
        description: 'This is the test:arithmatic command',
        help: "This command will add one and two. If the --negate flag\nis provided, then the result is negated.",
        aliases: ['arithmatic'],
        usage: ['2 2 --negate' => 'Add two plus two and then negate.'],
        options: [
        'negate' => ['description' => 'Whether or not the result should be negated. Default: false']
    ],
        params: [
        'one' => ['description' => 'The first number to add.'],
        'two' => ['description' => 'The other number to add. Default: 2']
    ],
    )]
    public function testArithmatic($one, $two = 2, array $options = ['negate' => false, 'unused' => 'bob'])
    {
        $result = $one + $two;
        if ($options['negate']) {
            $result = -$result;
        }

        // Integer return codes are exit codes (errors), so
        // return a the result as a string so that it will be printed.
        return "$result";
    }
}