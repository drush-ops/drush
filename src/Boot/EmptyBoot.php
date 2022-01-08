<?php

namespace Drush\Boot;

use Psr\Log\LoggerInterface;

/**
 * This is a do-nothing 'Boot' class that is used when there
 * is no site at --root, or when no root is specified.
 *
 * The 'empty' boot must be careful to never change state,
 * in case bootstrap code might later come along and set
 * a site (e.g. in command completion).
 */
class EmptyBoot extends BaseBoot
{
    public function validRoot($path): bool
    {
        return false;
    }

    public function bootstrapPhases(): array
    {
        return [
        DRUSH_BOOTSTRAP_DRUSH => '_drush_bootstrap_drush',
        ];
    }

    public function bootstrapInitPhases(): array
    {
        return [DRUSH_BOOTSTRAP_DRUSH];
    }
}
