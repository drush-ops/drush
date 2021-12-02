<?php

namespace Drush\Exceptions;

/**
 * Throw an exception indicating that a site install step is completed.
 */
class SiteInstallStepCompletedException extends \Exception
{
    public $installState = NULL;
    
    public function __construct(array $installState)
    {
        parent::__construct("Site install step completed.");
        $this->installState = $installState;
    }
}
