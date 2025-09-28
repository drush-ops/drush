<?php

namespace Unish;

use Drush\Application;
use Unish\Controllers\RuntimeController;

// @todo Consider extending UnishTestCase instead.
abstract class UnishApplicationTesterTestCase extends UnishIntegrationTestCase
{
    protected function getApplication(): Application
    {
        // @todo Simplify RuntimeController once all commands are using a Tester? Its singleton is still useful.
        return RuntimeController::instance()->application($this->webroot(), [$this->getDrush()]);
    }
}
