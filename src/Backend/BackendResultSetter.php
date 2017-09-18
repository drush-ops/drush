<?php
namespace Drush\Backend;

use Consolidation\AnnotatedCommand\Hooks\ExtractOutputInterface;

class BackendResultSetter implements ExtractOutputInterface
{
    public function extractOutput($structured_data)
    {
        $return = drush_backend_get_result();
        if (empty($return)) {
            drush_backend_set_result($structured_data);
        }
    }
}
