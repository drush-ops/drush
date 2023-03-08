<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class HandleRemoteCommands extends NoArgumentsBase
{
    const NAME = 'handle-remote-commands';
}
