<?php

namespace Custom\Library\AutowireTestClasses;

class AutowireTestService implements AutowireTestServiceInterface
{
    public function greeting(): string
    {
        return 'Hello World!';
    }
}
