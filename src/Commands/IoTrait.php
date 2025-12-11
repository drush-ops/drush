<?php

namespace Drush\Commands;

use Drush\Style\DrushStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Stores input, output and IO objects for easy access in commands.
 */
trait IoTrait
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected DrushStyle $io;

    public function setIo(InputInterface $input, OutputInterface $output): self
    {
        $this->input = $input;
        $this->output = $output;

        return $this;
    }

    public function io(): DrushStyle
    {
        if (isset($this->io)) {
            return $this->io;
        }

        $this->ensureIo();
        $this->io = new DrushStyle($this->input(), $this->output());

        return $this->io;
    }

    protected function input(): InputInterface
    {
        $this->ensureIo();

        return $this->input;
    }

    protected function output(): OutputInterface
    {
        $this->ensureIo();

        return $this->output;
    }

    protected function ensureIo(): void
    {
        if (!isset($this->input) || !isset($this->output)) {
            throw new \RuntimeException('The input and output objects have not been set. Please call setIo() before calling io().');
        }
    }
}
