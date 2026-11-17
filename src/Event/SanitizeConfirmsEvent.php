<?php

declare(strict_types=1);

namespace Drush\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched by sql:sanitize before it asks for confirmation.
 *
 * Subscribers describe what they are about to do with addMessage().
 */
final class SanitizeConfirmsEvent extends Event
{
    /**
     * @param string[] $messages
     */
    public function __construct(
        private readonly InputInterface $input,
        private array $messages = [],
    ) {
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

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
