<?php

namespace Drush\Event;

use Symfony\Contracts\EventDispatcher\Event;

/*
 * A custom event, for adding cache:clear types.
 */

final class CacheClearEvent extends Event
{
    public function __construct(
        protected array $types,
    ) {
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function addType(string $name, callable $callback): self
    {
        $this->types[$name] = $callback;
        return $this;
    }
}
