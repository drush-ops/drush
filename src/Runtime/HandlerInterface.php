<?php

declare(strict_types=1);

namespace Drush\Runtime;

/**
 * @file
 * Handler interface
 */

/**
 * HandlerInterface represents a PHP system handler (e.g. the error reporting
 * handler, the shutdown handler) that may be globally installed.
 */
interface HandlerInterface
{
    public function installHandler();
}
