<?php

declare(strict_types=1);

/**
 * Marker interface for tasks that use the IO trait
 */

namespace Drush\Symfony;

use Symfony\Component\Console\Input\InputAwareInterface;
use Consolidation\AnnotatedCommand\State\SavableState;

interface IOAwareInterface extends OutputAwareInterface, InputAwareInterface, SavableState
{
}
