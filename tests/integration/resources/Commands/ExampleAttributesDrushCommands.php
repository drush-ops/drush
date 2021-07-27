<?php
namespace Drush\Commands;

/**
 * Borrowed from https://github.com/beejeebus/annotated-command/blob/58e677d96b70845ce54bf0c367ddd09568f10044/tests/src/ExampleAttributesCommandFile.php.
 */
class ExampleAttributesDrushCommands extends DrushCommands
{
    #[DrushAttributes(
        aliases: ['c'],
        command: 'my:echo',
        description: 'This is the my:echo command',
        help: "This command will concatenate two parameters. If the --flip flag\nis provided, then the result is the concatenation of two and one.",
        options: [
            'flip' => 'Whether or not the second parameter should come first in the result.',
        ],
        usages: [
            'bet alpha --flip' => 'Concatenate "alpha" and "bet".'
        ],
    )]
    public function myEcho($one, $two = '', array $options = ['flip' => false])
    {
        if ($options['flip']) {
            return "{$two}{$one}";
        }
        return "{$one}{$two}";
    }

    #[DrushAttributes(
        aliases: ['arithmatic'],
        command: 'test:arithmatic',
        description: 'This is the test:arithmatic command',
        help: "This command will add one and two. If the --negate flag is provided, then the result is negated.",
        options: [
            'negate' => 'Whether or not the result should be negated.',
            'unused' => 'Gotta provide a description',
        ],
        params: [
            'one' => 'The first number to add.',
            'two' => 'The other number to add.',
        ],
        usages: [
            '2 2' => 'Add two plus two',
            '2 2 --negate' => 'Add two plus two and then negate.'
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

    #[DrushAttributes(
        hook: 'post-command test:arithmatic',
        description: 'Add a text after test:arithmatic command',
    )]
    public function postArithmatic()
    {
        $this->output->writeln('HOOKED');
    }
}
