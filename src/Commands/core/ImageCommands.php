<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class ImageCommands extends DrushCommands
{
    #[Deprecated(replacement: 'ImageDeriveCommand::NAME')]
    const DERIVE = 'image:derive';
    #[Deprecated(replacement: 'ImageFlushCommand::NAME')]
    const FLUSH = ImageFlushCommand::NAME;
}
