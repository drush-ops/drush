<?php

declare(strict_types=1);

namespace Drupal\woot;

final class AutowireTestService implements AutowireTestServiceInterface
{
    public function __toString(): string
    {
        return 'Hello World!';
    }
}
