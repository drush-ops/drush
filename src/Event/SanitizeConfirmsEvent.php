<?php

namespace Drush\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\EventDispatcher\Event;

/*
 * A custom event, for prompting the user about candidate sanitize operations.
 *
 * Listeners should add their confirm messages via addMessage().
 */

final class SanitizeConfirmsEvent extends Event
{
    public function __construct(
        protected InputInterface $input,
        protected array $messages = [],
    ) {
    }

    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function addMessage(string $message): self
    {
        $this->messages[] = $message;
        return $this;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function setMessages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }
}
