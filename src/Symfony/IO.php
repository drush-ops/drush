<?php

declare(strict_types=1);

namespace Drush\Symfony;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Consolidation\AnnotatedCommand\State\State;

trait IO
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    public function currentState()
    {
        return new class($this, $this->input, $this->output, $this->io) implements State {
            protected $obj;
            protected $input;
            protected $output;
            protected $io;

            public function __construct($obj, $input, $output, $io)
            {
                $this->obj = $obj;
                $this->input = $input;
                $this->output = $output;
                $this->io = $io;
            }

            public function restore()
            {
                $this->obj->restoreState($this->input, $this->output, $this->io);
            }
        };
    }

    // This should typically only be called by State::restore()
    public function restoreState(?InputInterface $input = null, ?OutputInterface $output = null, ?SymfonyStyle $io = null)
    {
        $this->setInput($input);
        $this->setOutput($output);
        $this->io = $io;

        return $this;
    }

    public function setInput(InputInterface $input): void
    {
        if ($input != $this->input) {
            $this->io = null;
        }
        $this->input = $input;
    }

    /**
     * @return InputInterface
     */
    protected function input()
    {
        if (!isset($this->input)) {
            $this->setInput(new ArgvInput());
        }
        return $this->input;
    }

    public function setOutput(OutputInterface $output)
    {
        if ($output != $this->output) {
            $this->io = null;
        }
        $this->output = $output;

        return $this;
    }

    /**
     * @return OutputInterface
     */
    protected function output()
    {
        if (!isset($this->output)) {
            $this->setOutput(new NullOutput());
        }
        return $this->output;
    }

    /**
     * @return OutputInterface
     */
    protected function stderr()
    {
        $output = $this->output();
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }
        return $output;
    }

    /**
     * Provide access to SymfonyStyle object.
     *
     * @deprecated Use a style injector instead
     *
     * @return SymfonyStyle
     *
     * @see https://symfony.com/blog/new-in-symfony-2-8-console-style-guide
     */
    protected function io()
    {
        if (!$this->io) {
            $this->io = new SymfonyStyle($this->input(), $this->output());
        }
        return $this->io;
    }

    /**
     * @param string $text
     */
    protected function writeln($text)
    {
        $this->output()->writeln($text);
    }
}
