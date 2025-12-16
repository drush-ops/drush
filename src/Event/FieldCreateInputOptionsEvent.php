<?php

namespace Drush\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\Event;

/*
 * A custom event, for modifying input options on the field:create.
 */

final class FieldCreateInputOptionsEvent extends Event
{
    public function __construct(
        protected InputInterface $input,
        protected OutputInterface $output,
    ) {
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
