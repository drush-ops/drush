<?php

namespace Drush\Commands;

class Validators
{
    public static function entityLoad(array $ids, string $entityType): void
    {
        // @todo It is a burden for the caller to inject the entityTypeManager.
        $loaded = \Drupal::entityTypeManager()->getStorage($entityType)->loadMultiple($ids);
        if ($missing = array_diff($ids, array_keys($loaded))) {
            $msg = dt('Unable to load the !type: !str', ['!type' => $entityType, '!str' => implode(', ', $missing)]);
            throw new \Exception($msg);
        }
    }
}
