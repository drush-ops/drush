<?php
namespace Drupal\woot\Commands;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This is an annotated version of the example Symfony Console command
 * from the documentation.
 *
 * See: http://symfony.com/doc/2.7/components/console/introduction.html#creating-a-basic-command
 */
class AnnotatedGreetCommand extends AnnotatedCommand
{
    /**
     * Greet someone
     *
     * @command annotated:greet
     * @arg string $name Who do you want to greet?
     * @option boolean $yell If set, the task will yell in uppercase letters
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        if ($name) {
            $text = 'Hello '.$name;
        } else {
            $text = 'Hello';
        }

        if ($input->getOption('yell')) {
            $text = strtoupper($text);
        }

        $output->writeln($text);
    }
}
