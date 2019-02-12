<?php

namespace Unish\Batch;

use Drush\Drush;

class UnitBatchOperations
{
    public static function operate(&$context)
    {
        $context['message'] = "!!! ArrayObject does its job.";

        for ($i = 0; $i < 5; $i++) {
            Drush::logger()->info("Iteration $i");
        }
        $context['finished'] = 1;
    }

    public static function finish()
    {
        // Restore php limits.
        // TODO.
    }
}
