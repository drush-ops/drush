<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class DeployHookCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use DeployHookStatusCommand::NAME')]
    const HOOK_STATUS = 'deploy:hook-status';
    #[Deprecated(reason: 'Use DeployHookCommand::NAME')]
    const HOOK = 'deploy:hook';
    #[Deprecated(reason: 'Use DeployHookBatchProcessCommand::NAME')]
    const BATCH_PROCESS = 'deploy:batch-process';
    #[Deprecated(reason: 'Use DeployHookMarkCompleteCommand::NAME')]
    const MARK_COMPLETE = 'deploy:mark-complete';
}
