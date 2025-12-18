<?php

namespace Drush\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\Event;

/*
 * A custom event, for changing the field:create config values.
 */

final class FieldCreateFieldConfigValuesEvent extends Event
{
    public function __construct(
        protected array $values,
        protected InputInterface $input,
        protected OutputInterface $output,
    ) {
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
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
