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
            // Store some results for post-processing in the 'finished' callback.
            // The contents of 'results' will be available as $results in the
            // 'finished' function.
            $context['results'][] = $i;
        }
        $context['finished'] = 1;
    }

    public static function finish($success, $results, $operations)
    {
        Drush::logger()->success("Result count is " . count($results));
    }
}
