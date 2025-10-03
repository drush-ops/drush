<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\UnstructuredData;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;
use Drush\Drush;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drush\Boot\DrupalBootLevels;

final class JsonapiCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use JsonapiGetCommand::NAME')]
    const GET = 'jn:get';
}
