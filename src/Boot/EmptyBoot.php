<?php

declare(strict_types=1);

namespace Drush\Boot;

/**
 * This is a do-nothing 'Boot' class that is used when there
 * is no site at Drupal root, or when no root is specified.
 *
 * The 'empty' boot must be careful to never change state,
 * in case bootstrap code might later come along and set
 * a site (e.g. in command completion).
 */
class EmptyBoot extends BaseBoot
{
    public function validRoot(?string $path): bool
    {
        return false;
    }

    public function bootstrapPhases(): array
    {
        return [
            DrupalBootLevels::NONE => '_drush_bootstrap_drush',
        ];
    }

    public function bootstrapInitPhases(): array
    {
        return [DrupalBootLevels::NONE];
    }
}
